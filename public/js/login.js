document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("loginForm");
  const messageDiv = document.getElementById("loginMessage");

  const closeButton = document.querySelector(".close-btn");
  
  if (closeButton) {
    closeButton.addEventListener("click", () => {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
    });
  }

  function sanitize(str) {
    const element = document.createElement("div");
    if (str) {
      element.innerText = str;
      element.textContent = str;
    }
    return element.innerHTML;
  }

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    let email = document.getElementById("email").value;
    let password = document.getElementById("password").value;
      
    email = sanitize(email);
    
    const formData = new FormData(form);
    formData.set("email", email);
    formData.set("password", password); //parola originara 
    
    fetch("http://localhost/IRI_Ballerina_Cappuccina/api/login", {
      method: "POST",
      body: formData,
      credentials: 'include', // FOARTE IMPORTANT pentru cookies
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest", // Indica ca este o cerere AJAX
      },
    })
      .then(async (response) => {
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || "An error occurred during login.");
        }

        return data;
      })
      .then((data) => {
        messageDiv.textContent = data.message || "Login successful!";
        messageDiv.className = data.success
          ? "alert alert-success"
          : "alert alert-danger";
        messageDiv.style.display = "block";

        if (data.success) {
          // Login reusit, acum verificam daca utilizatorul este admin
          setTimeout(() => {
            checkIfAdmin();
          }, 500);
        }
      })
      .catch((error) => {
        console.error("Login error:", error);
        messageDiv.textContent = error.message;
        messageDiv.className = "alert alert-danger";
        messageDiv.style.display = "block";
      });
  });
  
  // Functie pentru verificarea daca utilizatorul este admin
  function checkIfAdmin() {
    fetch("http://localhost/IRI_Ballerina_Cappuccina/api/auth/status", {
      method: "GET",
      credentials: 'include',
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
    })
    .then(response => response.json())
    .then(data => {
      if (data.authenticated) {
        // Verificam dacă utilizatorul este admin
        if (data.user && data.user.role === "admin") {
          // Utilizator admin - redirectionare către dashboard
          window.location.href = "/IRI_Ballerina_Cappuccina/public/admin/dashboard";
        } else {
          // Utilizator normal - redirectionare normala
          window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
        }
      } else {
        // Dacă nu e autentificat (ar fi ciudat sa ajunga aici), redirectionam la pagina principala
        window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
      }
    })
    .catch(error => {
      console.error("Error checking admin status:", error);
      // in caz de eroare, redirectionam către pagina principala
      window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
    });
  }
});