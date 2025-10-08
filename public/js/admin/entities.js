let currentUser = null;
let entities = [];
let entitiesTemplate;

let categories = [];
let currentCategory = "";
let currentStatus = "";

Handlebars.registerHelper("getStatusText", function (status) {
  switch (status) {
    case "pending":
      return "Pending";
    case "approved":
      return "Approved";
    case "rejected":
      return "Rejected";
    default:
      return status;
  }
});

Handlebars.registerHelper("if_eq", function (a, b, opts) {
  if (a === b) {
    return opts.fn(this);
  } else {
    return opts.inverse(this);
  }
});

async function loadCategories() {
  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/categories`,
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (!response.ok) {
      console.error("Eroare la încărcarea categoriilor");
      return;
    }

    categories = await response.json();
    populateCategoryDropdown();
  } catch (error) {
    console.error("Eroare la încărcarea categoriilor:", error);
  }
}

function populateCategoryDropdown() {
  const dropdown = document.getElementById("categoryFilter");

  while (dropdown.options.length > 1) {
    dropdown.remove(1);
  }

  categories.forEach((category) => {
    const option = document.createElement("option");
    option.value = category.name;
    option.textContent = category.name;
    dropdown.appendChild(option);
  });
}

document
  .getElementById("categoryFilter")
  .addEventListener("change", function (e) {
    currentCategory = e.target.value;
    filterEntities();
  });

document
  .getElementById("statusFilter")
  .addEventListener("change", function (e) {
    currentStatus = e.target.value;
    filterEntities();
  });

async function filterEntities() {
  try {
    let filteredEntities = [];

    if (currentCategory) {
      const response = await fetch(
        `http://localhost/IRI_Ballerina_Cappuccina/api/entities/${currentCategory}`,
        {
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      if (response.status === 403) {
        displayError("Nu aveți permisiuni pentru a accesa lista de entități");
        return;
      }

      filteredEntities = await response.json();
    } else {
      const response = await fetch(
        "http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities",
        {
          credentials: "include",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      if (response.status === 403) {
        displayError("Nu aveți permisiuni pentru a accesa lista de entități");
        return;
      }

      filteredEntities = await response.json();
    }

    if (currentStatus) {
      filteredEntities = filteredEntities.filter(
        (entity) => entity.status === currentStatus
      );
    }

    entities = filteredEntities;
    displayEntities(entities);
  } catch (error) {
    console.error("Eroare la filtrarea entităților:", error);
    displayError("Nu s-au putut filtra entitățile");
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

async function loadEntities() {
  const hasAccess = await checkAdminAccess();
  if (!hasAccess) return;

  if (!entitiesTemplate) {
    const source = document.getElementById("entities-template").innerHTML;
    entitiesTemplate = Handlebars.compile(source);
  }

  await loadCategories();

  try {
    const response = await fetch(
      "http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities",
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (response.status === 403) {
      displayError("Nu aveți permisiuni pentru a accesa lista de entități");
      return;
    }

    entities = await response.json();
    displayEntities(entities);
  } catch (error) {
    console.error("Eroare la încărcarea entităților:", error);
    displayError("Nu s-au putut încărca entitățile");
  }
}

function displayEntities(entitiesToDisplay) {
  const tbody = document.getElementById("entitiesTableBody");

  tbody.innerHTML = entitiesTemplate({ entities: entitiesToDisplay });

  document.querySelectorAll(".approve-entity-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const entityId = this.getAttribute("data-id");
      approveEntity(entityId);
    });
  });

  document.querySelectorAll(".reject-entity-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const entityId = this.getAttribute("data-id");
      rejectEntity(entityId);
    });
  });

  document.querySelectorAll(".delete-entity-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const entityId = this.getAttribute("data-id");
      deleteEntity(entityId);
    });
  });
}

function displayError(message) {
  const tbody = document.getElementById("entitiesTableBody");
  tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: red;">${message}</td></tr>`;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("ro-RO");
}

document.getElementById("search").addEventListener("input", function (e) {
  const searchTerm = e.target.value.toLowerCase();
  const filteredEntities = entities.filter(
    (entity) =>
      entity.name.toLowerCase().includes(searchTerm) ||
      (entity.description &&
        entity.description.toLowerCase().includes(searchTerm))
  );
  displayEntities(filteredEntities);
});

async function approveEntity(entityId) {
  if (!confirm("Sigur vrei să aprobi această entitate?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities/${entityId}/approve`,
      {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
      }
    );

    if (response.status === 403) {
      alert("Nu aveți permisiuni pentru a aproba entități");
      return;
    }

    if (response.ok) {
      loadEntities();
      alert("Entitate aprobată cu succes!");
    } else {
      alert("Eroare la aprobarea entității");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la aprobarea entității");
  }
}

async function rejectEntity(entityId) {
  if (!confirm("Sigur vrei să respingi această entitate?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities/${entityId}/reject`,
      {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
      }
    );

    if (response.status === 403) {
      alert("Nu aveți permisiuni pentru a respinge entități");
      return;
    }

    if (response.ok) {
      loadEntities();
      alert("Entitate respinsă cu succes!");
    } else {
      alert("Eroare la respingerea entității");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la respingerea entității");
  }
}

async function deleteEntity(entityId) {
  if (!confirm("Sigur vrei să ștergi această entitate?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/entities/${entityId}`,
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
      alert("Nu aveți permisiuni pentru a șterge entități");
      return;
    }

    if (response.ok) {
      loadEntities();
      alert("Entitate ștearsă cu succes!");
    } else {
      alert("Eroare la ștergerea entității");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la ștergerea entității");
  }
}

function exportEntities() {
  const format = document.getElementById("exportFormat").value;

  if (entities.length === 0) {
    alert("Nu există entități de exportat!");
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
  const headers = ["ID", "Nume", "Descriere", "Status"];
  const csvContent = [
    headers.join(","),
    ...entities.map((entity) =>
      [
        entity.id,
        `"${entity.name}"`,
        `"${entity.description || ""}"`,
        `"${getStatusText(entity.status)}"`,
      ].join(",")
    ),
  ].join("\n");

  downloadFile(csvContent, "entitati.csv", "text/csv");
}

function getStatusText(status) {
  switch (status) {
    case "pending":
      return "Pending";
    case "approved":
      return "Approved";
    case "rejected":
      return "Rejected";
    default:
      return status;
  }
}

function exportToJSON() {
  const exportData = entities.map((entity) => ({
    id: entity.id,
    name: entity.name,
    description: entity.description || "",
    status: entity.status,
    statusText: getStatusText(entity.status),
  }));

  const jsonContent = JSON.stringify(exportData, null, 2);
  downloadFile(jsonContent, "entitati.json", "application/json");
}

function exportToPDF() {
  const pdfContent = generatePDFContent();
  const blob = new Blob([pdfContent], { type: "application/pdf" });
  downloadFile(blob, "entitati.pdf", "application/pdf");
}

function generatePDFContent() {
  const currentDate = new Date().toLocaleDateString("ro-RO");
  const currentTime = new Date().toLocaleTimeString("ro-RO");

  let pdfData = "%PDF-1.4\n";

  pdfData += "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n\n";

  pdfData +=
    "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n\n";

  pdfData +=
    "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n\n";

  let content = "BT\n";
  content += "/F1 16 Tf\n";
  content += "50 750 Td\n";
  content += "(Lista Entități) Tj\n";

  content += "/F1 10 Tf\n";
  content += "0 -30 Td\n";
  content += `(Exportat pe: ${currentDate} la ${currentTime}) Tj\n`;

  content += "0 -20 Td\n";
  content += `(Total entități: ${entities.length}) Tj\n`;

  content += "/F1 12 Tf\n";
  content += "0 -40 Td\n";
  content += "(ID    Nume                Status          Descriere) Tj\n";

  content += "/F1 10 Tf\n";
  let yOffset = -20;
  entities.forEach((entity, index) => {
    if (index > 40) return;

    content += `0 ${yOffset} Td\n`;
    const line = `${entity.id.toString().padEnd(6)} ${entity.name
      .substring(0, 20)
      .padEnd(20)} ${getStatusText(entity.status).padEnd(15)} ${
      entity.description ? entity.description.substring(0, 30) : "-"
    }`;
    content += `(${line}) Tj\n`;
    yOffset = -15;
  });

  content += "ET\n";

  const contentLength = content.length;
  pdfData += `4 0 obj\n<<\n/Length ${contentLength}\n>>\nstream\n${content}\nendstream\nendobj\n\n`;

  pdfData +=
    "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n\n";

  pdfData += "xref\n0 6\n";
  pdfData += "0000000000 65535 f \n";
  pdfData += "0000000009 65535 n \n";
  pdfData += "0000000074 65535 n \n";
  pdfData += "0000000120 65535 n \n";
  pdfData += "0000000179 65535 n \n";
  pdfData += `0000000${(pdfData.length + 50)
    .toString()
    .padStart(6, "0")} 00000 n \n`;

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
loadEntities();
