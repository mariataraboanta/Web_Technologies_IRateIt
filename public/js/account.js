function initAccountPage() {
  const profileBtn = document.getElementById("profileBtn");
  const reviewsBtn = document.getElementById("reviewsBtn");
  const profileSection = document.getElementById("profileSection");
  const reviewsSection = document.getElementById("reviewsSection");
  const reviewsList = document.getElementById("reviewsList");

  const profilePicture = document.getElementById("profilePicture");
  const changeProfilePictureBtn = document.getElementById(
    "changeProfilePictureBtn"
  );
  const profilePictureInput = document.getElementById("profilePictureInput");
  const removeProfilePictureBtn = document.getElementById(
    "removeProfilePictureBtn"
  );

  let currentUserId = null;
  let currentUser = null;
  let userReviews = [];
  let isEditing = false;

  Handlebars.registerHelper("formatDate", function (dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString("ro-RO", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  });

  Handlebars.registerHelper("stars", function (rating) {
    let stars = "";
    for (let i = 1; i <= 5; i++) {
      if (i <= rating) {
        stars += "★";
      } else {
        stars += "☆";
      }
    }
    return stars;
  });

  function initProfilePicture() {
    changeProfilePictureBtn.addEventListener("click", function () {
      profilePictureInput.click();
    });

    profilePictureInput.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        uploadProfilePicture(file);
      }
    });

    removeProfilePictureBtn.addEventListener("click", function () {
      if (confirm("Ești sigur că vrei să ștergi poza de profil?")) {
        removeProfilePicture();
      }
    });
  }

  function validateImageFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = [
      "image/jpeg",
      "image/jpg",
      "image/png"
    ];

    if (file.size > maxSize) {
      alert("The file is too large. Maximum size is 5MB.");
      return false;
    }

    if (!allowedTypes.includes(file.type)) {
      alert(
        "Invalid file type. Only JPG, JPEG, and PNG files are allowed."
      );
      return false;
    }

    return true;
  }

  async function uploadProfilePicture(file) {
    if (!validateImageFile(file)) {
      return;
    }

    showUploadProgress("Image loading...");

    const formData = new FormData();
    formData.append("profile_picture", file);

    try {
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/account/profile-picture`,
        {
          method: "POST",
          credentials: "include",
          body: formData,
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      const result = await response.json();

      if (response.ok) {
        if (currentUser) {
          currentUser.profile_picture = result.image_url;
        }

        displayProfilePicture();

        showUploadSuccess("Success!");
      } else {
        showUploadError(
          "Error: " +
            (result.message || "Unknown error occurred")
        );
      }
    } catch (error) {
      console.error("Error:", error);
      showUploadError("Error uploading image. Please try again later.");
    } finally {
      profilePictureInput.value = "";
      setTimeout(clearUploadStatus, 3000);
    }
  }

  async function removeProfilePicture() {
    removeProfilePictureBtn.disabled = true;
    removeProfilePictureBtn.textContent = "Se șterge...";

    try {
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/account/profile-picture`,
        {
          method: "DELETE",
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      const result = await response.json();

      if (response.ok) {
        if (currentUser) {
          currentUser.profile_picture = null;
        }

        displayProfilePicture();

        alert("The profile picture has been removed!");
      } else {
        alert(
          "Error: " +
            (result.message || "Unknown error occurred")
        );
      }
    } catch (error) {
      console.error("Error:", error);
      alert("Error removing profile picture. Please try again later.");
    } finally {
      removeProfilePictureBtn.disabled = false;
      removeProfilePictureBtn.textContent = "Delete profile picture";
    }
  }

  function showUploadProgress(message) {
    clearUploadStatus();
    const progressDiv = document.createElement("div");
    progressDiv.className = "upload-progress";
    progressDiv.textContent = message;
    document.querySelector(".profile-picture-section").appendChild(progressDiv);
  }

  function showUploadSuccess(message) {
    clearUploadStatus();
    const successDiv = document.createElement("div");
    successDiv.className = "upload-progress";
    successDiv.textContent = message;
    successDiv.style.background = "#e8f5e8";
    successDiv.style.color = "#2e7d32";
    document.querySelector(".profile-picture-section").appendChild(successDiv);
  }

  function showUploadError(message) {
    clearUploadStatus();
    const errorDiv = document.createElement("div");
    errorDiv.className = "upload-error";
    errorDiv.textContent = message;
    document.querySelector(".profile-picture-section").appendChild(errorDiv);
  }

  function clearUploadStatus() {
    const existing = document.querySelector(".upload-progress, .upload-error");
    if (existing) {
      existing.remove();
    }
  }

  function displayProfilePicture() {
    if (currentUser && currentUser.profile_picture) {
      profilePicture.src = currentUser.profile_picture + "?t=" + Date.now();
      removeProfilePictureBtn.style.display = "inline-block";
    } else {
      profilePicture.src = "/IRI_Ballerina_Cappuccina/app/uploads/images.png";
      removeProfilePictureBtn.style.display = "none";
    }
  }

  window.deleteReview = async function (reviewId) {
    if (
      !confirm(
        "Are you sure you want to delete this review? This action cannot be undone."
      )
    ) {
      return;
    }

    const deleteBtn = document.querySelector(`[data-review-id="${reviewId}"]`);
    if (deleteBtn) {
      deleteBtn.disabled = true;
      deleteBtn.textContent = "Deleting...";
    }

    try {
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/reviews/${reviewId}`,
        {
          method: "DELETE",
          credentials: "include",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      const result = await response.json();

      if (response.ok) {
        alert("The review has been removed!");
        await reloadUserReviews();
        displayReviews();
      } else {
        alert(
          "Error: " +
            (result.message || "Unknown error occurred")
        );
        if (deleteBtn) {
          deleteBtn.disabled = false;
          deleteBtn.textContent = "Delete";
        }
      }
    } catch (error) {
      console.error("Error:", error);
      alert("Error deleting review. Please try again later.");
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.textContent = "Delete";
      }
    }
  };

  //preluare date utilizator 
  async function reloadUserReviews() {
    try {
      const res = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/account`,
        {
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      if (res.ok) {
        const data = await res.json();
        userReviews = data.reviews || [];
      } else {
        console.error("Error loading user reviews");
      }
    } catch (error) {
      console.error("Error:", error);
    }
  }

  async function fetchCurrentUserId() {
    try {
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/auth/status`,
        {
          credentials: "include",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );
      if (!response.ok) throw new Error("Error fetching user status");

      const data = await response.json();
      if (!data.authenticated) {
        alert("You are not authenticated.");
        window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
        return false;
      }

      currentUserId = data.user.id;
      return true;
    } catch (error) {
      console.error("Error:", error);
      alert("Error at authentication.");
      return false;
    }
  }

  async function loadAccountData() {
    try {
      const res = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/account`,
        {
          credentials: "include",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      if (res.status === 401) {
        window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
        return;
      }

      if (res.ok) {
        const data = await res.json();
        currentUser = data.user;
        userReviews = data.reviews || [];
        displayUserData();
      } else {
        console.error("Error loading account data");
        alert("Error loading account data. Please try again later.");
      }
    } catch (error) {
      console.error("Error:", error);
      alert("Error loading account data. Please try again later.");
    }
  }

  function displayUserData() {
    if (currentUser) {
      document.getElementById("userName").textContent = currentUser.username;
      document.getElementById("userEmail").textContent = currentUser.email;

      const joinedDate = new Date(currentUser.created_at).toLocaleDateString(
        "ro-RO",
        {
          year: "numeric",
          month: "long",
          day: "numeric",
        }
      );
      document.getElementById("userJoined").textContent = joinedDate;

      displayProfilePicture();
    }
  }

  function toggleEditMode() {
    const userNameElement = document.getElementById("userName");
    const userEmailElement = document.getElementById("userEmail");
    const editBtn = document.getElementById("editProfileBtn");

    if (!isEditing) {
      userNameElement.textContent = "";

      const usernameInput = document.createElement("input");
      usernameInput.type = "text";
      usernameInput.id = "editUsername";
      usernameInput.value = currentUser.username;
      usernameInput.maxLength = 50;
      userNameElement.appendChild(usernameInput);

      userEmailElement.textContent = "";

      const emailInput = document.createElement("input");
      emailInput.type = "email";
      emailInput.id = "editEmail";
      emailInput.value = currentUser.email;
      emailInput.maxLength = 100;
      userEmailElement.appendChild(emailInput);

      editBtn.textContent = "Save changes";
      editBtn.classList.add("save-mode");
      isEditing = true;
    } else {
      saveUserData();
    }
  }

  async function saveUserData() {
    const newUsername = document.getElementById("editUsername").value.trim();
    const newEmail = document.getElementById("editEmail").value.trim();
    const editBtn = document.getElementById("editProfileBtn");

    if (!newUsername || !newEmail) {
      alert("Username and email cannot be empty.");
      return;
    }

    if (!isValidEmail(newEmail)) {
      alert("Email invalid");
      return;
    }

    try {
      editBtn.disabled = true;
      editBtn.textContent = "Saving...";

      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/account`,
        {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "include",
          body: JSON.stringify({
            username: newUsername,
            email: newEmail,
          }),
        }
      );

      const result = await response.json();

      if (response.ok) {
        currentUser.username = newUsername;
        currentUser.email = newEmail;

        displayUserData();
        editBtn.textContent = "Edit profile";
        editBtn.classList.remove("save-mode");
        isEditing = false;

        reloadUserReviews().then(() => {
          if (reviewsSection.classList.contains("active")) {
            displayReviews();
          }
        });

        alert("The profile has been successfully updated!");
      } else {
        if (response.status === 409) {
          alert("Username already used.");
        } else {
          alert(
            "Error: " +
              (result.message || "Unknown error occurred")
          );
        }
      }
    } catch (error) {
      console.error("Eroare de rețea:", error);
      alert("Error at saving user data. Please try again later.");
    } finally {
      editBtn.disabled = false;
      if (isEditing) {
        editBtn.textContent = "Save changes";
      }
    }
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function displayReviews() {
    reviewsList.innerHTML = "";

    if (userReviews.length === 0) {
      reviewsList.innerHTML = "<p>No reviews yet.</p>";
      return;
    }

    const groupedReviews = [];

    userReviews.forEach((entry) => {
      let existing = groupedReviews.find(
        (r) => r.user === entry.user_name && r.created_at === entry.created_at
      );

      if (!existing) {
        existing = {
          user: entry.user_name,
          created_at: entry.created_at,
          id: entry.id,
          traits: [],
        };
        groupedReviews.push(existing);
      }

      existing.traits.push({
        id: entry.id,
        name: entry.trait_name,
        rating: entry.rating,
        comment: entry.comment,
        entity_name: entry.entity_name || "Unknown Entity",
      });
    });

    try {
      const templateSource =
        document.getElementById("review-template").innerHTML;
      const template = Handlebars.compile(templateSource);
      const renderedHtml = template({ reviews: groupedReviews });
      reviewsList.innerHTML = renderedHtml;
    } catch (error) {
      console.error(
        "Error:",
        error
      );
      reviewsList.innerHTML = `<p class="error">Error at loading reviews</p>`;
    }
  }

  initProfilePicture();

  fetchCurrentUserId().then((isAuthenticated) => {
    if (isAuthenticated) {
      profileSection.classList.add("active");
      loadAccountData();
    }
  });

  profileBtn.addEventListener("click", function () {
    profileSection.classList.add("active");
    reviewsSection.classList.remove("active");

    if (isEditing) {
      displayUserData();
      document.getElementById("editProfileBtn").textContent =
        "Edit profile";
      document.getElementById("editProfileBtn").classList.remove("save-mode");
      isEditing = false;
    }
  });

  reviewsBtn.addEventListener("click", function () {
    reviewsSection.classList.add("active");
    profileSection.classList.remove("active");
    displayReviews();
  });

  document
    .getElementById("editProfileBtn")
    .addEventListener("click", toggleEditMode);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initAccountPage);
} else {
  initAccountPage();
}
