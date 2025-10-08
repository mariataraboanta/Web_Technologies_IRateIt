document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("registerForm");
  const messageDiv = document.getElementById("registerMessage");

  const closeButton = document.querySelector(".close-btn");

  if (closeButton) {
    closeButton.addEventListener("click", () => {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
    });
  }

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    document.getElementById("usernameError").textContent = "";
    document.getElementById("emailError").textContent = "";
    document.getElementById("passwordError").textContent = "";
    document.getElementById("confirmPasswordError").textContent = "";

    let username = document.getElementById("username").value.trim();
    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value;
    let confirmPassword = document.getElementById("confirmPassword").value;

    //validari de baza
    if (!username) {
      document.getElementById("usernameError").textContent =
        "Username is required.";
      return;
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      document.getElementById("emailError").textContent =
        "Invalid email format.";
      return;
    }

    if (password.length < 8) {
      document.getElementById("passwordError").textContent =
        "Password needs to have at least 8 characters.";
      return;
    }

    if (password !== confirmPassword) {
      document.getElementById("confirmPasswordError").textContent =
        "Passwords do not match.";
      return;
    }

    // NU mai facem hash pe client - trimitem parola in clar către server
    // Hash-ul se va face pe server cu password_hash()
    const formData = new FormData();
    formData.set("username", username);
    formData.set("email", email);
    formData.set("password", password); // Parola in clar - va fi hash-uita pe server

    fetch("http://localhost/IRI_Ballerina_Cappuccina/api/register", {
      method: "POST",
      body: formData,
      credentials: 'include',
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
    })
      .then(async (response) => {
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || "An error appeared on the server.");
        }

        return data;
      })
      .then((data) => {
        messageDiv.textContent = data.message || "Respond from the server";
        messageDiv.className = data.success
          ? "alert alert-success"
          : "alert alert-danger";
        messageDiv.style.display = "block";

        if (data.success) {
          setTimeout(() => {
            window.location.href = "../public/login";
          }, 2000);
        }
      })
      .catch((error) => {
        messageDiv.textContent = error.message;
        messageDiv.className = "alert alert-danger";
        messageDiv.style.display = "block";
      });
  });
});