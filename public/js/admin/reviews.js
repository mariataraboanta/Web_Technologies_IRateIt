let currentUser = null;
let reviews = [];
let entities = [];
let traits = [];
let currentEntity = "";
let reviewsTemplate;

Handlebars.registerHelper("formatDate", function (dateString) {
  const date = new Date(dateString);
  return (
    date.toLocaleDateString("ro-RO") + " " + date.toLocaleTimeString("ro-RO")
  );
});

Handlebars.registerHelper("displayStars", function (rating) {
  return "★".repeat(rating) + "☆".repeat(5 - rating);
});

async function loadTraits() {
  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/traits`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (!response.ok) {
      console.error("Eroare la încărcarea trăsăturilor");
      return;
    }

    traits = await response.json();
  } catch (error) {
    console.error("Eroare la încărcarea trăsăturilor:", error);
  }
}

async function loadEntities() {
  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (!response.ok) {
      console.error("Eroare la încărcarea entităților");
      return;
    }

    entities = await response.json();
    populateEntityDropdown();
  } catch (error) {
    console.error("Eroare la încărcarea entităților:", error);
  }
}

function populateEntityDropdown() {
  const dropdown = document.getElementById("entityFilter");

  while (dropdown.options.length > 1) {
    dropdown.remove(1);
  }

  entities.forEach((entity) => {
    const option = document.createElement("option");
    option.value = entity.id;
    option.textContent = entity.name;
    dropdown.appendChild(option);
  });
}

document
  .getElementById("entityFilter")
  .addEventListener("change", function (e) {
    currentEntity = e.target.value;
    filterReviews();
  });

async function filterReviews() {
  if (!currentEntity) {
    loadReviews();
    return;
  }

  try {
    // gasim entitatea selectata din lista de entitati deja incarcate
    const selectedEntity = entities.find((e) => e.id == currentEntity);

    if (!selectedEntity) {
      displayError("Entitatea selectată nu a fost găsită");
      return;
    }

    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${selectedEntity.category_name}/${selectedEntity.id}`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (response.status === 403) {
      displayError("Nu aveți permisiuni pentru a accesa lista de recenzii");
      return;
    }

    const flatReviews = await response.json();
    groupReviews(flatReviews);
  } catch (error) {
    console.error("Eroare la filtrarea recenziilor:", error);
    displayError("Nu s-au putut filtra recenziile");
  }
}

async function checkAdminAccess() {
  try {
    const response = await fetch(
      "http://localhost/IRI_Ballerina_Cappuccina/api/auth/status",
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    const data = await response.json();

    if (!data.authenticated) {
      window.location.href = "/IRI_Ballerina_Cappuccina/public/login";
      return false;
    }

    currentUser = data.user;

    if (currentUser.role !== "admin") {
      alert(
        "Nu aveți permisiuni de administrator pentru a accesa această pagină."
      );
      window.location.href = "/IRI_Ballerina_Cappuccina/public/categories";
      return false;
    }

    return true;
  } catch (error) {
    console.error("Eroare la verificarea accesului:", error);
    alert("A apărut o eroare. Vă rugăm să încercați din nou.");
    return false;
  }
}

async function loadReviews() {
  const hasAccess = await checkAdminAccess();
  if (!hasAccess) return;

  //compilam templateul
  if (!reviewsTemplate) {
    const source = document.getElementById("reviews-template").innerHTML;
    reviewsTemplate = Handlebars.compile(source);
  }

  await loadTraits();
  await loadEntities();

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/reviews`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (response.status === 403) {
      displayError("Nu aveți permisiuni pentru a accesa lista de recenzii");
      return;
    }

    const flatReviews = await response.json();
    groupReviews(flatReviews);
  } catch (error) {
    console.error("Eroare la încărcarea recenziilor:", error);
    displayError("Nu s-au putut încărca recenziile");
  }
}

function groupReviews(flatReviews) {
  const groupedReviews = [];

  flatReviews.forEach((entry) => {
    let existing = groupedReviews.find(
      (r) => r.user === entry.user_name && r.created_at === entry.created_at
    );

    if (!existing) {
      existing = {
        id: entry.id,
        entity_id: entry.entity_id,
        user: entry.user_name,
        created_at: entry.created_at,
        traits: [],
      };
      groupedReviews.push(existing);
    }

    const trait = traits.find((t) => t.id == entry.trait_id);

    existing.traits.push({
      name: trait ? entry.trait_name : `ID ${entry.trait_id}`,
      rating: entry.rating,
      comment: entry.comment,
    });
  });

  reviews = groupedReviews;
  displayReviews(reviews);
}

function displayReviews(reviewsToDisplay) {
  const tbody = document.getElementById("reviewsTableBody");

  //folosim templateul sa afisam recenziile
  tbody.innerHTML = reviewsTemplate({ reviews: reviewsToDisplay });

  document.querySelectorAll(".delete-review-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const reviewId = this.getAttribute("data-id");
      deleteReview(reviewId);
    });
  });
}

function displayError(message) {
  const tbody = document.getElementById("reviewsTableBody");
  tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 20px; color: red;">${message}</td></tr>`;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return (
    date.toLocaleDateString("ro-RO") + " " + date.toLocaleTimeString("ro-RO")
  );
}

document.getElementById("search").addEventListener("input", function (e) {
  const searchTerm = e.target.value.toLowerCase();
  const filteredReviews = reviews.filter(
    (review) =>
      review.user.toLowerCase().includes(searchTerm) ||
      review.traits.some(
        (trait) =>
          trait.name.toLowerCase().includes(searchTerm) ||
          review.traits.some(
            (trait) =>
              trait.comment && trait.comment.toLowerCase().includes(searchTerm)
          )
      )
  );
  displayReviews(filteredReviews);
});

async function deleteReview(reviewId) {
  if (!confirm("Sigur vrei să ștergi această recenzie?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/reviews/${reviewId}`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
      }
    );
    if (response.status === 403) {
      alert("Nu aveți permisiuni pentru a șterge recenzii");
      return;
    }

    if (response.ok) {
      loadReviews();
      alert("Recenzie ștearsă cu succes!");
    } else {
      alert("Eroare la ștergerea recenziei");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la ștergerea recenziei");
  }
}

function exportReviews() {
  const format = document.getElementById("exportFormat").value;

  if (reviews.length === 0) {
    alert("Nu există recenzii de exportat!");
    return;
  }

  switch (format) {
    case "csv":
      exportToCSV();
      break;
    case "json":
      exportToJSON();
      break;
    case "pdf":
      exportToPDF();
      break;
    default:
      alert("Format de export invalid!");
  }
}

function exportToCSV() {
  const headers = ["Utilizator", "Traituri", "Rating", "Comentariu", "Data"];
  const csvContent = [
    headers.join(","),
    ...reviews.flatMap((review) =>
      review.traits.map((trait) =>
        [
          `"${review.user}"`,
          `"${trait.name}"`,
          trait.rating,
          `"${trait.comment || ""}"`,
          `"${formatDate(review.created_at)}"`,
        ].join(",")
      )
    ),
  ].join("\n");

  downloadFile(csvContent, "recenzii.csv", "text/csv");
}

function exportToJSON() {
  const exportData = reviews.map((review) => ({
    user: review.user,
    entity_id: review.entity_id,
    created_at: review.created_at,
    traits: review.traits.map((trait) => ({
      name: trait.name,
      rating: trait.rating,
      comment: trait.comment || "",
    })),
  }));

  const jsonContent = JSON.stringify(exportData, null, 2);
  downloadFile(jsonContent, "recenzii.json", "application/json");
}

function exportToPDF() {
  const pdfContent = generatePDFContent();
  const blob = new Blob([pdfContent], { type: "application/pdf" });
  downloadFile(blob, "recenzii.pdf", "application/pdf");
}

function generatePDFContent() {
  const currentDate = new Date().toLocaleDateString("ro-RO");
  const currentTime = new Date().toLocaleTimeString("ro-RO");

  let pdfData = "%PDF-1.4\n";

  //Catalog
  pdfData += "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n\n";

  //Pages
  pdfData +=
    "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n\n";

  //Page
  pdfData +=
    "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n\n";

  //Content stream
  let content = "BT\n";
  content += "/F1 16 Tf\n";
  content += "50 750 Td\n";
  content += "(Lista Recenzii) Tj\n";

  content += "/F1 10 Tf\n";
  content += "0 -30 Td\n";
  content += `(Exportat pe: ${currentDate} la ${currentTime}) Tj\n`;

  content += "0 -20 Td\n";
  content += `(Total recenzii: ${reviews.length}) Tj\n`;

  //Header tabel
  content += "/F1 12 Tf\n";
  content += "0 -40 Td\n";
  content += "(Utilizator    Traituri    Rating    Data) Tj\n";

  //Date recenzii
  content += "/F1 10 Tf\n";
  let yOffset = -20;
  reviews.forEach((review, index) => {
    if (index > 20) return;

    review.traits.forEach((trait) => {
      content += `0 ${yOffset} Td\n`;
      const line = `${review.user.substring(0, 15).padEnd(15)} ${trait.name
        .substring(0, 15)
        .padEnd(15)} ${String(trait.rating).padEnd(8)} ${formatDate(
        review.created_at
      )}`;
      content += `(${line}) Tj\n`;
      yOffset = -15;
    });
    yOffset = -20;
  });

  content += "ET\n";

  const contentLength = content.length;
  pdfData += `4 0 obj\n<<\n/Length ${contentLength}\n>>\nstream\n${content}\nendstream\nendobj\n\n`;

  //Font
  pdfData +=
    "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n\n";

  //Tabel de referinta
  pdfData += "xref\n0 6\n";
  pdfData += "0000000000 65535 f \n";
  pdfData += "0000000009 65535 n \n";
  pdfData += "0000000074 65535 n \n";
  pdfData += "0000000120 65535 n \n";
  pdfData += "0000000179 65535 n \n";
  pdfData += `0000000${(pdfData.length + 50)
    .toString()
    .padStart(6, "0")} 00000 n \n`;

  //Trailer
  pdfData += "trailer\n<<\n/Size 6\n/Root 1 0 R\n>>\n";
  pdfData += "startxref\n";
  pdfData += (pdfData.length + 20).toString();
  pdfData += "\n%%EOF\n";

  return pdfData;
}

function downloadFile(content, filename, mimeType) {
  const blob = new Blob([content], { type: mimeType });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
}

loadReviews();
