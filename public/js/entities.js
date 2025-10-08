function initEntitiesPage() {
  setupEntityForm();
  setupModal();
  loadEntities();
}

function setupEntityForm() {
  const form = document.getElementById("addEntityForm");
  if (!form) {
    console.error("The form has not been found.");
    return;
  }

  //pentru upload de imagini la new entity from
  const fileInput = document.getElementById("entityImage");
  const dropArea = document.getElementById("dropArea");
  const imagePreview = document.getElementById("imagePreview");
  const previewImg = document.getElementById("previewImg");
  const uploadPlaceholder = document.getElementById("uploadPlaceholder");
  const removeImageBtn = document.getElementById("removeImage");
  const browseButton = document.querySelector(".upload-btn");

  let selectedFile = null;

  //previne propagarea evenimentelor de click pe butonul de browse
  if (browseButton) {
    browseButton.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }

  dropArea.addEventListener("click", function (e) {
    if (
      (e.target === dropArea || e.target === uploadPlaceholder) &&
      !e.target.closest("label") &&
      !e.target.closest("button")
    ) {
      fileInput.click();
    }
  });

  //selectarea fisierului
  fileInput.addEventListener("change", function () {
    const file = this.files[0];
    if (file) {
      selectedFile = file;
      displayImagePreview(file);
    }
  });

  //drag and drop
  ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
    dropArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ["dragenter", "dragover"].forEach((eventName) => {
    dropArea.addEventListener(eventName, highlight, false);
  });

  ["dragleave", "drop"].forEach((eventName) => {
    dropArea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
    dropArea.classList.add("dragover");
  }

  function unhighlight() {
    dropArea.classList.remove("dragover");
  }

  dropArea.addEventListener("drop", handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const file = dt.files[0];

    if (file && file.type.match("image.*")) {
      selectedFile = file;
      fileInput.files = dt.files;
      displayImagePreview(file);
    }
  }

  function displayImagePreview(file) {
    const reader = new FileReader();

    reader.onload = function (e) {
      previewImg.src = e.target.result;
      uploadPlaceholder.style.display = "none";
      imagePreview.style.display = "block";
    };

    reader.readAsDataURL(file);
  }

  function showError(message, timeout = 4000) {
    const errorEl = document.getElementById("customError");
    errorEl.textContent = message;
    errorEl.style.display = "block";
    setTimeout(() => {
      errorEl.style.display = "none";
    }, timeout);
  }
  //stergere imagine
  removeImageBtn.addEventListener("click", function () {
    selectedFile = null;
    fileInput.value = "";
    previewImg.src = "";
    uploadPlaceholder.style.display = "flex";
    imagePreview.style.display = "none";
  });

  //submit
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = document.getElementById("entityName").value.trim();
    const description = document
      .getElementById("entityDescription")
      .value.trim();

    if (!name || !description) return;

    try {
      const formData = new FormData();
      formData.append("name", name);
      formData.append("description", description);

      //adauga imaginea daca este selectata
      if (selectedFile) {
        formData.append("image", selectedFile);
      }

      const pathParts = window.location.pathname.split("/");
      const categoryName = decodeURIComponent(pathParts[pathParts.length - 1]);

      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${categoryName}`,
        {
          method: "POST",
          body: formData,
          credentials: "include",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      if (response.status === 401) {
        window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
        return;
      }
      else if (response.status === 409) {
        showError("Entity with this name already exists.");
        return ;
      }

      if (!response.ok) throw new Error("Error.");

      //resetare formular
      document.getElementById("entityName").value = "";
      document.getElementById("entityDescription").value = "";
      selectedFile = null;
      fileInput.value = "";
      previewImg.src = "";
      uploadPlaceholder.style.display = "flex";
      imagePreview.style.display = "none";

      document.getElementById("entityModal").classList.add("hidden");

      await loadEntities();
    } catch (error) {
      console.error("Eroare:", error);
      showError("An error appeared while adding the entity.");
    }
  });
}

function setupModal() {
  const modal = document.getElementById("entityModal");
  const openModalButton = document.getElementById("addEntityButton");
  const closeModalButton = document.querySelector(".close-button");

  if (!modal || !openModalButton || !closeModalButton) {
    console.error("Modal elements not found.");
    return;
  }
  modal.classList.add("hidden");

  openModalButton.addEventListener("click", (e) => {
    e.preventDefault();
    modal.classList.remove("hidden");
  });

  closeModalButton.addEventListener("click", () => {
    modal.classList.add("hidden");
  });

  window.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.classList.add("hidden");
    }
  });
}

async function loadEntities() {
  const container = document.getElementById("entitiesContainer");
  const templateSource = document.getElementById("entity-template").innerHTML;
  const template = Handlebars.compile(templateSource);
  const pathParts = window.location.pathname.split("/");
  const categoryName = decodeURIComponent(pathParts[pathParts.length - 1]);

  try {
    const res = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${categoryName}/approved`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (res.status === 401) {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
      return;
    }

    if (!res.ok) {
      throw new Error(`HTTP error! status: ${res.status}`);
    }

    const entities = await res.json();

    if (entities.length === 0) {
      container.innerHTML =
        "<p style='text-align: center;'>Entities not found</p>";
      return;
    }

    const processedEntities = entities.map((entity) => {
      return {
        ...entity,
        category_name: entity.category_name || categoryName,
        review_count: entity.review_count || 0,
      };
    });

    // Helper pentru a afisa stelele in functie de rating
    Handlebars.registerHelper("stars", function (rating) {
      const stars = parseInt(rating || 0);
      return "★".repeat(stars) + "☆".repeat(5 - stars);
    });

    container.innerHTML = template({ entities: processedEntities });

    document.querySelectorAll(".entity-card").forEach((card) => {
      const entityId = card.getAttribute("data-entity-id");

      card.addEventListener("click", function (e) {
        navigateToEntityDetail(entityId, categoryName);
      });
    });
  } catch (err) {
    console.error("Error", err);
    container.innerHTML =
      "<p style='color: red;'>An error appeared</p>";
  }

  function navigateToEntityDetail(entityId, categoryName) {
    const url = `/IRI_Ballerina_Cappuccina/public/entities/${categoryName}/${entityId}`;
    window.location.href = url;
  }
}

initEntitiesPage();
