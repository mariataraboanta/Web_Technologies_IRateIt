window.addEventListener("DOMContentLoaded", async () => {
  const header = document.getElementById("header");

  try {
    const response = await fetch(
      "/IRI_Ballerina_Cappuccina/public/pages/components/header.html"
    );
    if (!response.ok) throw new Error("The header could not be loaded.");

    const html = await response.text();
    header.innerHTML = html;

    await checkAuthorized();
    logoutButton();
    initAdminMenu();

    requestAnimationFrame(() => {
      header.classList.add("loaded");
    });
  } catch (e) {
    header.innerHTML =
      '<div class="error-header">An error appeared</div>';
    header.classList.add("loaded");
  }
});

function initAdminMenu() {
  const adminToggle = document.getElementById("admin-toggle");
  const adminDropdown = document.getElementById("admin-dropdown");

  if (!adminToggle || !adminDropdown) return;

  //meniu de admin
  adminToggle.addEventListener("click", function (e) {
    e.stopPropagation();
    adminToggle.classList.toggle("active");
    adminDropdown.classList.toggle("show");
  });

  //meniu de admin - inchide dropdown-ul cand se face click in afara lui
  document.addEventListener("click", function (e) {
    if (!adminToggle.contains(e.target) && !adminDropdown.contains(e.target)) {
      adminToggle.classList.remove("active");
      adminDropdown.classList.remove("show");
    }
  });

  //meniu de admin care se inchde cand se da click pe un link din dropdown
  adminDropdown.addEventListener("click", function (e) {
    if (e.target.tagName === "A") {
      adminToggle.classList.remove("active");
      adminDropdown.classList.remove("show");
    }
  });
}

async function checkAuthorized() {
  try {
    const url = "http://localhost/IRI_Ballerina_Cappuccina/api/auth/status";

    let response = await fetch(url, {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "include",
    });

    const data = await response.json();

    const loginLink = document.querySelector('a[href="login"]');
    if (loginLink) {
      loginLink.setAttribute("href", "/IRI_Ballerina_Cappuccina/public/login");
    }
    const logoutLink = document.querySelector('a[href="logout"]');
    const addReview = document.querySelector('a[href="addReview"]');
    const accountLink = document.getElementById("account-btn");

    //elemente pentru meniul admin
    const adminMenu = document.querySelector(".admin-menu");
    const publicCategories = document.querySelector(
      'a[href="/IRI_Ballerina_Cappuccina/public/categories"]'
    );

    if (data.authenticated) {
      if (loginLink) loginLink.style.display = "none";
      if (logoutLink) logoutLink.style.display = "block";
      if (addReview) {
        if (data.user && data.user.role === "admin") {
          addReview.style.display = "none";
        } else {
          addReview.style.display = "block";
          addReview.setAttribute(
            "href",
            "/IRI_Ballerina_Cappuccina/public/addReview"
          );
        }
      }
      if (accountLink) accountLink.style.display = "block";

      //logica pentru meniul admin
      if (data.user && data.user.role === "admin") {
        if (adminMenu) adminMenu.style.display = "block";
        if (publicCategories) publicCategories.style.display = "none";
      } else {
        if (adminMenu) adminMenu.style.display = "none";
        if (publicCategories) publicCategories.style.display = "inline-block";
      }
    } else {
      if (loginLink) loginLink.style.display = "block";
      if (logoutLink) logoutLink.style.display = "none";
      if (addReview) addReview.style.display = "none";
      if (accountLink) accountLink.style.display = "none";

      //ascunde meniul admin pentru utilizatori neautentificati
      if (adminMenu) adminMenu.style.display = "none";
      if (publicCategories) publicCategories.style.display = "inline-block";
    }
  } catch (error) {
    const loginLink = document.querySelector('a[href="login"]');
    const logoutLink = document.querySelector('a[href="logout"]');
    const addReview = document.querySelector('a[href="addReview"]');
    const accountLink = document.getElementById("account-btn");
    const adminMenu = document.querySelector(".admin-menu");
    const publicCategories = document.querySelector(
      'a[href="/IRI_Ballerina_Cappuccina/public/categories"]'
    );

    //in caz de eroare, ascunde totul pentru siguranta
    if (loginLink) loginLink.style.display = "block";
    if (logoutLink) logoutLink.style.display = "none";
    if (addReview) addReview.style.display = "none";
    if (accountLink) accountLink.style.display = "none";
    if (adminMenu) adminMenu.style.display = "none";
    if (publicCategories) publicCategories.style.display = "inline-block";
  }
}

function logoutButton() {
  const logoutLink = document.querySelector('a[href="logout"]');
  if (logoutLink) {
    logoutLink.addEventListener("click", async (e) => {
      e.preventDefault();
      try {
        const response = await fetch(
          "http://localhost/IRI_Ballerina_Cappuccina/api/logout",
          {
            method: "POST",
            credentials: "include",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
          }
        );

        setTimeout(() => {
          window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
        }, 300);
      } catch (error) {
        alert("Logout failed.");
      }
    });
  }
}
