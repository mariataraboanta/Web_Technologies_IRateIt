async function loadReviews(entityId, categoryName) {
  const container = document.getElementById("reviewsContainer");

  if (!container) {
    console.error("Containerul pentru recenzii nu a fost găsit.");
    return;
  }

  try {
    //fetch pentru recenzii
    const res = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${categoryName}/${entityId}`,
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

    const flatReviews = await res.json();
    if (flatReviews.length === 0) {
      container.innerHTML =
        "<p style='text-align: center;'>Nu există recenzii pentru această entitate.</p>";
      return;
    }

    if (flatReviews && flatReviews.length > 0 && flatReviews[0].name) {
      document.querySelector(".section-title").textContent =
        flatReviews[0].name + " - Recenzii";
    }

    const groupedReviews = [];

    // Gruparea recenziilor
    flatReviews.forEach((entry) => {
      let existing = groupedReviews.find(
        (r) => r.user === entry.user_name && r.created_at === entry.created_at
      );

      if (!existing) {
        existing = {
          user: entry.user_name,
          created_at: entry.created_at,
          traits: [],
          images: [],
          profile_picture: entry.profile_picture_path || null,
          profile_picture_filename: entry.profile_picture_filename || null,
        };
        groupedReviews.push(existing);
      }

      existing.traits.push({
        name: entry.trait_name,
        rating: entry.rating,
        comment: entry.comment,
      });

      if (entry.images && Array.isArray(entry.images)) {
        entry.images.forEach((imagePath) => {
          if (imagePath && !existing.images.includes(imagePath)) {
            existing.images.push(imagePath);
          }
        });
      }
    });

    displayTraitAverages(groupedReviews);

    Handlebars.registerHelper("stars", function (rating) {
      let starsHtml = "";
      for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
          starsHtml += '<span class="star filled">★</span>';
        } else {
          starsHtml += '<span class="star empty">☆</span>';
        }
      }
      return new Handlebars.SafeString(starsHtml);
    });

    Handlebars.registerHelper("hasImages", function (images) {
      return images && Array.isArray(images) && images.length > 0;
    });

    Handlebars.registerHelper("reviewImages", function (images) {
      if (!images || !Array.isArray(images) || images.length === 0) {
        return "";
      }

      let imagesHtml = '<div class="review-images">';
      images.forEach((imagePath) => {
        if (imagePath) {
          const imageUrl = `http://localhost${imagePath}`;
          imagesHtml += `
            <div class="review-image-container">
              <img src="${imageUrl}" alt="Review image" class="review-image" onclick="openImageModal('${imageUrl}')">
            </div>
          `;
        }
      });
      imagesHtml += "</div>";

      return new Handlebars.SafeString(imagesHtml);
    });

    Handlebars.registerHelper(
      "profilePicture",
      function (profilePicturePath, userName) {
        const firstLetter = userName.charAt(0).toUpperCase();
        const nameHtml = `<div class="profile-username">${userName}</div>`;

        if (profilePicturePath) {
          const imageUrl = `http://localhost${profilePicturePath}`;
          return new Handlebars.SafeString(`
            <div class="profile-wrapper">
              <div class="profile-picture">
                <img src="${imageUrl}" alt="Profile picture of ${userName}" class="profile-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="profile-avatar" style="display: none;">${firstLetter}</div>
              </div>
              ${nameHtml}
            </div>
          `);
        } else {
          return new Handlebars.SafeString(`
            <div class="profile-wrapper">
              <div class="profile-picture">
                <div class="profile-avatar">${firstLetter}</div>
              </div>
              ${nameHtml}
            </div>
          `);
        }
      }
    );

    const template = document.getElementById("review-template").innerHTML;
    const compiled = Handlebars.compile(template);

    const html = compiled({ reviews: groupedReviews });
    container.innerHTML = html;

    addImageModal();
  } catch (err) {
    console.error("Eroare la încărcare recenzii:", err);
    container.innerHTML =
      "<p style='color: red;'>A apărut o eroare la încărcarea recenziilor.</p>";
  }
}

function addImageModal() {
  if (document.getElementById("imageModal")) {
    return;
  }

  const modal = document.createElement("div");
  modal.id = "imageModal";
  modal.className = "image-modal hidden";
  modal.innerHTML = `
    <div class="image-modal-content">
    <img id="modalImage" src="" alt="Review image">
    </div>
  `;

  document.body.appendChild(modal);

  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      closeImageModal();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeImageModal();
    }
  });
}

function openImageModal(imageUrl) {
  const modal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");

  if (modal && modalImage) {
    modalImage.src = imageUrl;
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
  }
}

function closeImageModal() {
  const modal = document.getElementById("imageModal");
  if (modal) {
    modal.classList.add("hidden");
    document.body.style.overflow = "auto";
  }
}

async function initReviewsPage() {
  const entityId = getEntityIdFromUrl();
  const categoryName = getCategoryNameFromUrl();

  displayCategoryName(categoryName);

  try {
    const userResponse = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/auth/status`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
        },
      }
    );

    if (!userResponse.ok)
      throw new Error("Eroare la verificarea autentificării");

    const userData = await userResponse.json();

    if (userData.authenticated) {
      setupReviewForm(entityId, categoryName, userData.user.username);
      setupQuestionForm(entityId, categoryName, userData.user.username);
      setupAnswerForm(userData.user.username);
    } else {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
      return;
    }
  } catch (error) {
    console.error("Error fetching user data:", error);
    return;
  }
  setupModal();
  setupQAModals();
  fetchTraitsAndGenerateUI(categoryName);
  loadReviews(entityId, categoryName);
  loadQA(entityId, categoryName);
}

function displayCategoryName(categoryName) {
  const categoryElement = document.getElementById("categoryNameDisplay");
  if (categoryElement) {
    categoryElement.textContent = categoryName;
  }
}

function showError(id, message, timeout = 4000) {
  const errorEl = document.getElementById(id);
  errorEl.textContent = message;
  errorEl.style.display = "block";
  setTimeout(() => {
    errorEl.style.display = "none";
  }, timeout);
}

async function fetchTraitsAndGenerateUI(categoryName) {
  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/traits/${categoryName}`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
        },
      }
    );

    if (response.status === 401) {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
      return;
    }

    if (!response.ok) throw new Error("Eroare la preluarea trăsăturilor");

    const traits = await response.json();
    renderTraits(traits);
  } catch (error) {
    console.error("Error:", error);
  }
}

function renderTraits(traits) {
  const container = document.getElementById("traits-container");
  if (!container) {
    console.warn("Container for traits not found");
    return;
  }

  const templateSource = document.getElementById("traits-template").innerHTML;
  const template = Handlebars.compile(templateSource);

  const html = template({ traits });
  container.innerHTML = html;
}

function getEntityIdFromUrl() {
  const pathParts = window.location.pathname.split("/");
  return pathParts[pathParts.length - 1];
}

function getCategoryNameFromUrl() {
  const pathParts = window.location.pathname.split("/");
  return pathParts[4];
}

function setupReviewForm(entityId, categoryName, username) {
  const form = document.getElementById("addReviewForm");
  if (!form) {
    console.error("Formular de recenzie negăsit!");
    return;
  }

  // Seteaza numele utilizatorului în campul de input
  const userNameInput = document.getElementById("reviewUserName");
  if (userNameInput) {
    userNameInput.value = username || "";
    userNameInput.readOnly = true;
    userNameInput.classList.add("readonly-input");
  }

  // Selecteaza elementele necesare pentru preview-ul imaginilor
  const fileInput = document.getElementById("reviewImages");
  const dropArea = document.getElementById("dropArea");
  const imagesPreview = document.getElementById("imagesPreview");
  const previewImagesContainer = document.getElementById(
    "previewImagesContainer"
  );
  const uploadPlaceholder = document.getElementById("uploadPlaceholder");
  const removeAllImagesBtn = document.getElementById("removeAllImages");
  const browseButton = document.getElementById("browseButton");
  // Initializează un array pentru a stoca fisierele selectate
  let selectedFiles = [];

  // Resetare preview la incarcarea paginii
  if (uploadPlaceholder) uploadPlaceholder.style.display = "flex";
  if (imagesPreview) imagesPreview.style.display = "none";
  if (previewImagesContainer) previewImagesContainer.innerHTML = "";

  if (browseButton) {
    browseButton.addEventListener("click", function (e) {
      e.stopPropagation();
      e.preventDefault();
      if (fileInput) {
        fileInput.click();
      }
    });
  }

  // Adauga event listener pentru click pe drop area
  if (dropArea) {
    dropArea.addEventListener("click", function (e) {
      // Nu deschide file input dacă s-a facut click pe browse button sau pe alte controale
      if (
        e.target === dropArea ||
        (uploadPlaceholder &&
          uploadPlaceholder.contains(e.target) &&
          !e.target.closest("label") &&
          !e.target.closest("button"))
      ) {
        if (fileInput) {
          fileInput.click();
        }
      }
    });
  }

  // Event listener pentru input-ul de fisiere
  if (fileInput) {
    fileInput.addEventListener("change", function (e) {
      if (this.files.length > 0) {
        const newFiles = Array.from(this.files).filter((file) => {
          // validare tipul fisierului
          if (!file.type.startsWith("image/")) {
            console.warn("Skipping non-image file:", file.name);
            return false;
          }

          // (max 5MB)
          if (file.size > 5 * 1024 * 1024) {
            console.warn("File too large:", file.name);
            showError("customError1", `File ${file.name} too big. Max 5MB.`);
            return false;
          }

          return true;
        });

        if (newFiles.length > 0) {
          selectedFiles = selectedFiles.concat(newFiles);
          displayImagePreviews();
        }
      }

      // reset
      this.value = "";
    });
  }

  // Drag and drop handlers
  if (dropArea) {
    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      dropArea.addEventListener(
        eventName,
        function (e) {
          e.preventDefault();
          e.stopPropagation();
        },
        false
      );
    });

    ["dragenter", "dragover"].forEach((eventName) => {
      dropArea.addEventListener(
        eventName,
        function () {
          dropArea.classList.add("dragover");
        },
        false
      );
    });

    ["dragleave", "drop"].forEach((eventName) => {
      dropArea.addEventListener(
        eventName,
        function () {
          dropArea.classList.remove("dragover");
        },
        false
      );
    });

    dropArea.addEventListener(
      "drop",
      function (e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        const newFiles = Array.from(files).filter((file) => {
          if (!file.type.startsWith("image/")) {
            console.warn("Skipping non-image file:", file.name);
            return false;
          }
          if (file.size > 5 * 1024 * 1024) {
            console.warn("File too large:", file.name);
            showError("customError1", `File ${file.name} too big. Max 5MB.`);
            return false;
          }
          return true;
        });

        if (newFiles.length > 0) {
          selectedFiles = selectedFiles.concat(newFiles);
          displayImagePreviews();
        }
      },
      false
    );
  }

  // Event listener pentru butonul de stergere a tuturor imaginilor
  if (removeAllImagesBtn) {
    removeAllImagesBtn.addEventListener("click", function (e) {
      e.preventDefault();
      selectedFiles = [];
      displayImagePreviews();
    });
  }

  // Functia pentru afisarea previzualizarilor imaginilor
  function displayImagePreviews() {
    if (!imagesPreview || !previewImagesContainer || !uploadPlaceholder) {
      console.error("Preview containers missing", {
        imagesPreview: !!imagesPreview,
        previewImagesContainer: !!previewImagesContainer,
        uploadPlaceholder: !!uploadPlaceholder,
      });
      return;
    }

    if (selectedFiles.length === 0) {
      uploadPlaceholder.style.display = "flex";
      imagesPreview.style.display = "none";
      previewImagesContainer.innerHTML = "";
      return;
    }

    uploadPlaceholder.style.display = "none";
    imagesPreview.style.display = "block";
    previewImagesContainer.innerHTML = "";

    selectedFiles.forEach((file, index) => {
      if (!file.type.startsWith("image/")) {
        console.warn("Skipping non-image file:", file.name);
        return;
      }

      const reader = new FileReader();

      reader.onload = function (e) {
        const previewItem = document.createElement("div");
        previewItem.className = "preview-item";

        const img = document.createElement("img");
        img.src = e.target.result;
        img.className = "preview-image";
        img.alt = file.name;

        // Adauga event listeners pentru erori si succes la incarcare
        img.onerror = function () {
          console.error("Failed to load image:", file.name);
          previewItem.remove();
        };

        img.onload = function () {
          console.log("Image loaded successfully:", file.name);
        };

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "remove-image-btn";
        removeBtn.innerHTML = "&times;";
        removeBtn.title = "Șterge imaginea";

        // Adauga event listener pentru stergere
        removeBtn.addEventListener("click", function (e) {
          e.stopPropagation();
          e.preventDefault();

          const fileIndex = selectedFiles.indexOf(file);
          if (fileIndex > -1) {
            selectedFiles.splice(fileIndex, 1);
            displayImagePreviews();
          }
        });

        previewItem.appendChild(img);
        previewItem.appendChild(removeBtn);
        previewImagesContainer.appendChild(previewItem);
      };

      reader.onerror = function (error) {
        console.error("FileReader error for file:", file.name, error);
      };

      // Citeste fisierul ca URL de date
      reader.readAsDataURL(file);
    });
  }

  // Event listener pentru submit-ul formularului
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const traitDivs = document.querySelectorAll(
      "#traits-container .trait-rating"
    );
    let allTraitsValid = true;
    const traitsData = [];

    traitDivs.forEach((div) => {
      const traitId = div.dataset.traitId;
      const select = div.querySelector("select.trait-select");
      const comment = div.querySelector("textarea.trait-comment").value.trim();

      if (!select.value) {
        allTraitsValid = false;
      }

      traitsData.push({
        trait_id: traitId,
        rating: select.value,
        comment: comment,
      });
    });

    if (!allTraitsValid) {
      showError("customError1", "Complete all ratings!");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("user_name", username);

      traitsData.forEach((trait, index) => {
        formData.append(`traits[${index}][trait_id]`, trait.trait_id);
        formData.append(`traits[${index}][rating]`, trait.rating);
        formData.append(`traits[${index}][comment]`, trait.comment);
      });

      selectedFiles.forEach((file, index) => {
        formData.append(`images[${index}]`, file);
      });

      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${categoryName}/${entityId}`,
        {
          method: "POST",
          body: formData,
          credentials: "include",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
          },
        }
      );

      if (response.status === 401) {
        window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
        return;
      }

      if (!response.ok) throw new Error("Eroare la adăugare recenzie.");

      const result = await response.json();

      if (result.success) {
        traitDivs.forEach((div) => {
          div.querySelector("select.trait-select").value = "";
          div.querySelector("textarea.trait-comment").value = "";
        });

        selectedFiles = [];
        if (fileInput) fileInput.value = "";
        displayImagePreviews();

        document.getElementById("reviewModal").classList.add("hidden");
        await loadReviews(entityId, categoryName);
      } else {
        console.error("Eroare:", result.message);
        alert(
          "Eroare la adăugarea recenziei: " +
            (result.message || "Eroare necunoscută")
        );
      }
    } catch (error) {
      console.error("Eroare:", error);
      alert("Eroare la adăugarea recenziei: " + error.message);
    }
  });
}

function setupModal() {
  const modal = document.getElementById("reviewModal");
  const openModalButton = document.getElementById("addReviewButton");
  const closeModalButton = document.querySelector(".close-button");

  if (!modal || !openModalButton || !closeModalButton) {
    console.error("Nu s-au găsit elementele necesare pentru modal.");
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

function updateEntityRatingSummary(reviews) {
  let ratingSum = 0;
  let ratingCount = 0;

  reviews.forEach((review) => {
    if (Array.isArray(review.traits)) {
      review.traits.forEach((trait) => {
        if (trait.rating) {
          ratingSum += Number(trait.rating);
          ratingCount++;
        }
      });
    }
  });

  const avgRating = ratingCount > 0 ? ratingSum / ratingCount : 0;

  const ratingDisplay = document.getElementById("entityRating");
  const countDisplay = document.getElementById("reviewsCount");

  if (ratingDisplay) ratingDisplay.textContent = avgRating.toFixed(1);
  if (countDisplay) countDisplay.textContent = `${reviews.length}`;
}

function displayTraitAverages(reviews) {
  const traitSums = {};
  const traitCounts = {};

  reviews.forEach((review) => {
    if (Array.isArray(review.traits)) {
      review.traits.forEach((trait) => {
        if (!trait.name || trait.rating === undefined) return;

        if (!traitSums[trait.name]) {
          traitSums[trait.name] = 0;
          traitCounts[trait.name] = 0;
        }

        traitSums[trait.name] += Number(trait.rating);
        traitCounts[trait.name]++;
      });
    }
  });

  const traitAveragesContainer = document.getElementById("traitAverages");
  if (!traitAveragesContainer) return;

  const averageHtml = Object.keys(traitSums)
    .map((trait) => {
      const avg = traitSums[trait] / traitCounts[trait];
      return `<div class="trait-average-item" style="margin-bottom: 5px;">
                <strong>${trait}</strong>: 
                <span>${avg.toFixed(2)} / 5</span>
              </div>`;
    })
    .join("");

  traitAveragesContainer.innerHTML = `<h4 style="margin-bottom: 0.5rem;">Medii pe trăsături:</h4>${averageHtml}`;
}

async function loadQA(entityId, categoryName) {
  const container = document.getElementById("qaContainer");

  if (!container) {
    console.error("Containerul pentru Q&A nu a fost găsit.");
    return;
  }

  try {
    const res = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/qa/${categoryName}/${entityId}`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
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

    const questionsData = await res.json();

    if (questionsData.length === 0) {
      container.innerHTML =
        "<p style='text-align: center;'>Nu există întrebări pentru această entitate.</p>";
      return;
    }

    // Înregistrăm helper-ul pentru formatarea datei
    Handlebars.registerHelper("formatDate", function (date) {
      const options = { year: "numeric", month: "short", day: "numeric" };
      return new Date(date).toLocaleDateString("ro-RO", options);
    });

    // Înregistrăm helper-ul pentru numărarea răspunsurilor
    Handlebars.registerHelper("answersCount", function (answers) {
      return answers ? answers.length : 0;
    });

    const template = document.getElementById("question-template").innerHTML;
    const compiled = Handlebars.compile(template);

    const html = compiled({ questions: questionsData });
    container.innerHTML = html;

    // Adăugăm event listeners pentru butoanele de răspuns și vot
    setupQAEventListeners();
  } catch (err) {
    console.error("Eroare la încărcare Q&A:", err);
    container.innerHTML =
      "<p style='color: red;'>A apărut o eroare la încărcarea întrebărilor și răspunsurilor.</p>";
  }
}

// Configurarea modalelor pentru întrebări și răspunsuri
function setupQAModals() {
  // Modal pentru întrebări
  const questionModal = document.getElementById("questionModal");
  const askQuestionButton = document.getElementById("askQuestionButton");
  const closeQuestionModalButtons =
    questionModal.querySelectorAll(".close-button");

  if (askQuestionButton) {
    askQuestionButton.addEventListener("click", (e) => {
      e.preventDefault();
      questionModal.classList.remove("hidden");
    });
  }

  closeQuestionModalButtons.forEach((button) => {
    button.addEventListener("click", () => {
      questionModal.classList.add("hidden");
    });
  });

  // Modal pentru răspunsuri
  const answerModal = document.getElementById("answerModal");
  const closeAnswerModalButtons = answerModal.querySelectorAll(".close-button");

  closeAnswerModalButtons.forEach((button) => {
    button.addEventListener("click", () => {
      answerModal.classList.add("hidden");
    });
  });

  // Închidere modală la click în afara conținutului
  window.addEventListener("click", (e) => {
    if (e.target === questionModal) {
      questionModal.classList.add("hidden");
    }
    if (e.target === answerModal) {
      answerModal.classList.add("hidden");
    }
  });
}

// Configurarea event listener-elor pentru Q&A
function setupQAEventListeners() {
  // Event listener pentru butonul de răspuns
  document.querySelectorAll(".answer-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const questionId = this.getAttribute("data-question-id");
      const questionText =
        this.closest(".qa-card").querySelector(".question-text").textContent;

      // Completăm modalul de răspuns
      document.getElementById("questionId").value = questionId;
      document.getElementById("displayedQuestion").textContent = questionText;

      // Deschidem modalul
      document.getElementById("answerModal").classList.remove("hidden");
    });
  });

  // Event listeners pentru butonele de vot - optimizat
  document.querySelectorAll(".vote-btn").forEach((button) => {
    button.addEventListener("click", async function (e) {
      e.preventDefault();

      // Verificăm dacă butonul a fost deja apăsat
      if (this.classList.contains("voted")) {
        return;
      }

      const answerId = this.getAttribute("data-answer-id");
      const isUpvote = this.classList.contains("upvote");

      try {
        const response = await fetch(
          `http://localhost/IRI_Ballerina_Cappuccina/api/qa/vote/${answerId}`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
            },
            body: JSON.stringify({
              vote_type: isUpvote ? "upvote" : "downvote",
            }),
            credentials: "include",
          }
        );

        if (response.status === 401) {
          window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
          return;
        }

        if (!response.ok) {
          throw new Error("Eroare la votare");
        }

        const result = await response.json();

        if (result.success) {
          // Actualizăm contorul vizual
          const voteCount = this.querySelector(".vote-count");
          if (voteCount) {
            voteCount.textContent = parseInt(voteCount.textContent) + 1;
          }

          // Dezactivăm butonul după votare
          this.classList.add("voted");

          // Dezactivăm butonul opus pentru a preveni votarea dublă
          const answersSection = this.closest(".answer-actions");
          if (answersSection) {
            const oppositeBtn = isUpvote
              ? answersSection.querySelector(".downvote")
              : answersSection.querySelector(".upvote");

            if (oppositeBtn) {
              oppositeBtn.classList.add("disabled");
              oppositeBtn.disabled = true;
            }
          }
        } else {
          alert(result.message || "You cannot vote twice.");
        }
      } catch (error) {
        console.error("Eroare la votare:", error);
        alert("A apărut o eroare la procesarea votului.");
      }
    });
  });

  // Search pentru Q&A
  const qaSearchInput = document.getElementById("qaSearchInput");
  if (qaSearchInput) {
    qaSearchInput.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase();

      document.querySelectorAll(".qa-card").forEach((card) => {
        const questionText = card
          .querySelector(".question-text")
          .textContent.toLowerCase();
        const userName = card
          .querySelector(".question-user")
          .textContent.toLowerCase();

        // Căutare și în răspunsuri, dacă există
        let foundInAnswers = false;
        card.querySelectorAll(".answer-item").forEach((answer) => {
          const answerText = answer
            .querySelector(".answer-text")
            .textContent.toLowerCase();
          const answerUser = answer
            .querySelector(".answer-user")
            .textContent.toLowerCase();

          if (
            answerText.includes(searchTerm) ||
            answerUser.includes(searchTerm)
          ) {
            foundInAnswers = true;
          }
        });

        if (
          questionText.includes(searchTerm) ||
          userName.includes(searchTerm) ||
          foundInAnswers
        ) {
          card.style.display = "";
        } else {
          card.style.display = "none";
        }
      });
    });
  }
}

// Configurarea formularului pentru întrebări
function setupQuestionForm(entityId, categoryName, username) {
  const form = document.getElementById("askQuestionForm");
  if (!form) {
    console.error("Formular pentru întrebări negăsit!");
    return;
  }

  // Setăm numele utilizatorului
  const userNameInput = document.getElementById("questionUserName");
  if (userNameInput) {
    userNameInput.value = username || "";
    userNameInput.readOnly = true;
    userNameInput.classList.add("readonly-input");
  }

  // Gestionăm trimiterea formularului
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const questionText = document.getElementById("questionText").value.trim();

    if (!questionText) {
      showError("customError3", "Ask a question!");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("user_name", username);
      formData.append("question_text", questionText);
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/qa/${categoryName}/${entityId}`,
        {
          method: "POST",
          body: formData,
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
          },
        }
      );

      if (response.status === 401) {
        window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
        return;
      }

      if (!response.ok) {
        throw new Error("Eroare la adăugarea întrebării.");
      }

      const result = await response.json();

      if (result.success) {
        // Resetăm formularul
        document.getElementById("questionText").value = "";

        // Închidem modalul și reîncărcăm întrebările
        document.getElementById("questionModal").classList.add("hidden");
        await loadQA(entityId, categoryName);
      } else {
        console.error("Eroare:", result.message);
        alert(
          "Eroare la adăugarea întrebării: " +
            (result.message || "Eroare necunoscută")
        );
      }
    } catch (error) {
      console.error("Eroare:", error);
      alert("Eroare la adăugarea întrebării: " + error.message);
    }
  });
}

// Configurarea formularului pentru răspunsuri
function setupAnswerForm(username) {
  const submitAnswerForm = document.getElementById("submitAnswerForm");

  if (submitAnswerForm) {
    // Set username in the form
    const userNameInput = document.getElementById("answerUserName");
    if (userNameInput) {
      userNameInput.value = username || "";
      userNameInput.readOnly = true;
      userNameInput.classList.add("readonly-input");
    }

    submitAnswerForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      const questionId = document.getElementById("questionId").value;
      const answerText = document.getElementById("answerText").value.trim();

      if (!answerText) {
        showError("customError4", "Give an answer!");
        return;
      }

      try {
        const formData = new FormData();
        formData.append("user_name", username);
        formData.append("answer_text", answerText);

        const response = await fetch(
          `http://localhost/IRI_Ballerina_Cappuccina/api/qa/answer/${questionId}`,
          {
            method: "POST",
            body: formData,
            credentials: "include",
            headers: {
              "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
            },
          }
        );

        if (response.status === 401) {
          window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
          return;
        }

        if (!response.ok) {
          throw new Error("Eroare la adăugarea răspunsului.");
        }

        const result = await response.json();

        if (result.success) {
          // Resetăm formularul
          document.getElementById("answerText").value = "";

          // Închidem modalul și reîncărcăm Q&A
          document.getElementById("answerModal").classList.add("hidden");

          // Reîncărcăm întrebările pentru a afișa noul răspuns
          const entityId = getEntityIdFromUrl();
          const categoryName = getCategoryNameFromUrl();
          await loadQA(entityId, categoryName);
        } else {
          console.error("Eroare:", result.message);
          alert(
            "Eroare la adăugarea răspunsului: " +
              (result.message || "Eroare necunoscută")
          );
        }
      } catch (error) {
        console.error("Eroare:", error);
        alert("Eroare la adăugarea răspunsului: " + error.message);
      }
    });
  }
}

initReviewsPage();
