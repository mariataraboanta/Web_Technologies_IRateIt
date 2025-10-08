function initCategoriesPage() {
  setupCategoryForm();
  setupModal();
  loadCategories();
}

function showError(message, timeout = 4000) {
  const errorEl = document.getElementById("customError");
  errorEl.textContent = message;
  errorEl.style.display = "block";
  setTimeout(() => {
    errorEl.style.display = "none";
  }, timeout);
}
function setupCategoryForm() {
  const form = document.getElementById("addCategoryForm");
  if (!form) {
    console.error("Form not found!");
    return;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = document.getElementById("categoryName").value.trim();
    if (!name) return;

    const traitInputs = document.querySelectorAll('input[name="traits[]"]');
    const traits = [];
    traitInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) {
        traits.push(value);
      }
    });

    try {
      const formData = new FormData();
      formData.append("name", name);
      formData.append("traits", JSON.stringify(traits));

      const response = await fetch(
        "http://localhost/IRI_Ballerina_Cappuccina/api/categories",
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
      else if (response.status === 409){
        showError("Category with this name already exists.");
        return;
      }

      if (!response.ok) throw new Error("Error saving category.");

      //resetam formularul
      document.getElementById("categoryName").value = "";
      resetTraits();
      document.getElementById("categoryModal").classList.add("hidden");
      await loadCategories();
    } catch (error) {
      console.error("Eroare:", error);
      showError(
        "Error saving category. Please try again later."
      );
    }
  });
}

//verificam daca utilizatorul este autentificat
async function checkAuthStatus() {
  try {
    const response = await fetch(
      "http://localhost/IRI_Ballerina_Cappuccina/api/check",
      {
        method: "GET",
        credentials: "include",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    return response.ok;
  } catch (error) {
    console.error("Error:", error);
    return false;
  }
}

//modal pentru adaugarea unei categorii
function setupModal() {
  const modal = document.getElementById("categoryModal");
  const openModalButton = document.getElementById("addCategoryButton");
  const closeModalButton = document.querySelector(".close-button");

  if (!modal || !openModalButton || !closeModalButton) {
    console.error("Modal elements not found.");
    return;
  }

  modal.classList.add("hidden");

  openModalButton.addEventListener("click", async (e) => {
    e.preventDefault();

    const isLoggedIn = await checkAuthStatus();

    if (!isLoggedIn) {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
      return;
    }

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

// Functie care incarca categoriile din baza de date
async function loadCategories() {
  const container = document.getElementById("categoriesContainer");

  if (!container) {
    console.error("Container not found!");
    return;
  }

  try {
    const res = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/categories/approved`,
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

    if (!res.ok) throw new Error("Error fetching categories.");

    const categories = await res.json();
    window.allCategories = categories;

    const templateSource =
      document.getElementById("category-template").innerHTML;
    const template = Handlebars.compile(templateSource);
    const html = template({ categories });
    container.innerHTML = html;

    const categoryLinks = container.querySelectorAll(".category-button");
    categoryLinks.forEach((link) => {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        window.location.href = this.href;
      });
    });
  } catch (e) {
    console.error("Error:", e);
    container.innerHTML =
      '<div class="error">Categories could not load</div>';
  }
}

function addTrait() {
  const container = document.getElementById("traitsContainer");
  const group = document.createElement("div");
  group.className = "trait-group";

  const input = document.createElement("input");
  input.type = "text";
  input.name = "traits[]";
  input.placeholder = "Ex: Preț";
  input.required = true;

  const removeBtn = document.createElement("button");
  removeBtn.type = "button";
  removeBtn.textContent = "✖";
  removeBtn.onclick = () => group.remove();

  group.appendChild(input);
  group.appendChild(removeBtn);
  container.appendChild(group);
}

function removeTrait(btn) {
  const container = document.getElementById("traitsContainer");
  const groups = container.querySelectorAll(".trait-group");

  if (groups.length > 1) {
    btn.parentElement.remove();
  } else {
    showError("At least one trait!");
  }
}

function resetTraits() {
  const container = document.getElementById("traitsContainer");
  container.innerHTML = `
      <div class="trait-group">
          <input type="text" name="traits[]" placeholder="Ex: Calitate" required>
          <button type="button" onclick="removeTrait(this)">✖</button>
      </div>
  `;
}

initCategoriesPage();
