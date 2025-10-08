let currentUser = null;
let categories = [];
let categoriesTemplate;
let errorTemplate;

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

Handlebars.registerHelper("getActionButtons", function (category) {
  let buttons = "";

  if (category.status === "pending") {
    buttons += `
      <button class="btn-sm btn-success" onclick="approveCategory(${category.id})">Approve</button>
      <button class="btn-sm btn-warning" onclick="rejectCategory(${category.id})">Reject</button>
    `;
  } else if (category.status === "approved") {
    buttons += `
      <button class="btn-sm btn-warning" onclick="rejectCategory(${category.id})">Reject</button>
    `;
  } else if (category.status === "rejected") {
    buttons += `
      <button class="btn-sm btn-success" onclick="approveCategory(${category.id})">Approve</button>
    `;
  }

  buttons += `<button class="btn-sm btn-danger" onclick="deleteCategory(${category.id})">Delete</button>`;

  return new Handlebars.SafeString(buttons);
});

function initializeTemplates() {
  try {
    const templateSource = document.getElementById(
      "categories-template"
    ).innerHTML;
    categoriesTemplate = Handlebars.compile(templateSource);

    const errorSource = document.getElementById("error-template").innerHTML;
    errorTemplate = Handlebars.compile(errorSource);
  } catch (error) {
    console.error("Eroare la compilarea template-urilor:", error);
    alert("Eroare la inițializarea paginii. Vă rugăm reîncărcați pagina.");
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

async function loadCategories() {
  const hasAccess = await checkAdminAccess();
  if (!hasAccess) return;

  try {
    const response = await fetch(
      "http://localhost/IRI_Ballerina_Cappuccina/api/admin/categories",
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (response.status === 403) {
      displayError("Nu aveți permisiuni pentru a accesa lista de categorii");
      return;
    }

    categories = await response.json();
    displayCategories(categories);
  } catch (error) {
    console.error("Eroare la încărcarea categoriilor:", error);
    displayError("Nu s-au putut încărca categoriile");
  }
}

function displayCategories(categoriesToDisplay) {
  const tbody = document.getElementById("categoriesTableBody");

  if (!categoriesTemplate) {
    console.error("Template-ul nu a fost inițializat corect");
    tbody.innerHTML =
      '<tr><td colspan="5" style="text-align: center; padding: 20px;">Eroare la afișarea categoriilor</td></tr>';
    return;
  }

  const html = categoriesTemplate({ categories: categoriesToDisplay });
  tbody.innerHTML = html;
}

function displayError(message) {
  const tbody = document.getElementById("categoriesTableBody");

  if (errorTemplate) {
    tbody.innerHTML = errorTemplate({ message: message });
  } else {
    //fallback daca templateul nu e disponibil
    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: red;">${Handlebars.escapeExpression(
      message
    )}</td></tr>`;
  }
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleDateString("ro-RO");
}

document.getElementById("search").addEventListener("input", function (e) {
  const searchTerm = e.target.value.toLowerCase();
  const filteredCategories = categories.filter(
    (category) =>
      category.name.toLowerCase().includes(searchTerm) ||
      (category.description &&
        category.description.toLowerCase().includes(searchTerm))
  );
  displayCategories(filteredCategories);
});

function filterByStatus(status) {
  const filteredCategories =
    status === "all"
      ? categories
      : categories.filter((category) => category.status === status);
  displayCategories(filteredCategories);
}

async function approveCategory(categoryId) {
  if (!confirm("Sigur vrei să aprobi această categorie?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/categories/${categoryId}/approve`,
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
      alert("Nu aveți permisiuni pentru a aproba categorii");
      return;
    }

    if (response.ok) {
      loadCategories();
      alert("Categoria a fost aprobată cu succes!");
    } else {
      alert("Eroare la aprobarea categoriei");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la aprobarea categoriei");
  }
}

async function rejectCategory(categoryId) {
  if (!confirm("Sigur vrei să respingi această categorie?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/categories/${categoryId}/reject`,
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
      alert("Nu aveți permisiuni pentru a respinge categorii");
      return;
    }

    if (response.ok) {
      loadCategories();
      alert("Categoria a fost respinsă!");
    } else {
      alert("Eroare la respingerea categoriei");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la respingerea categoriei");
  }
}

async function deleteCategory(categoryId) {
  if (!confirm("Sigur vrei să ștergi această categorie?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/admin/categories/${categoryId}`,
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
      alert("Nu aveți permisiuni pentru a șterge categorii");
      return;
    }

    if (response.ok) {
      loadCategories();
      alert("Categorie ștearsă cu succes!");
    } else {
      alert("Eroare la ștergerea categoriei");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la ștergerea categoriei");
  }
}

function exportCategories() {
  const format = document.getElementById("exportFormat").value;

  if (categories.length === 0) {
    alert("Nu există categorii de exportat!");
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
  const headers = ["ID", "Nume"];
  const csvContent = [
    headers.join(","),
    ...categories.map((category) =>
      [category.id, `"${category.name}"`].join(",")
    ),
  ].join("\n");

  downloadFile(csvContent, "categorii.csv", "text/csv");
}

function exportToJSON() {
  const exportData = categories.map((category) => ({
    id: category.id,
    name: category.name,
  }));

  const jsonContent = JSON.stringify(exportData, null, 2);
  downloadFile(jsonContent, "categorii.json", "application/json");
}

function exportToPDF() {
  const pdfContent = generatePDFContent();
  const blob = new Blob([pdfContent], { type: "application/pdf" });
  downloadFile(blob, "categorii.pdf", "application/pdf");
}

function generatePDFContent() {
  const currentDate = new Date().toLocaleDateString("ro-RO");
  const currentTime = new Date().toLocaleTimeString("ro-RO");

  //initializare PDF cu versiunea de format
  let pdfData = "%PDF-1.4\n";

  //radacina ierarhiei de obiecte PDF - obiectul Catalog
  pdfData += "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n\n";

  //obiectul Pages cu lista de pagini
  pdfData +=
    "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n\n";

  //obiectul Page cu detalii despre pagina
  pdfData +=
    "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n\n";

  let content = "BT\n";
  content += "/F1 16 Tf\n";
  content += "50 750 Td\n";
  content += "(Lista Categorii) Tj\n";

  content += "/F1 10 Tf\n";
  content += "0 -30 Td\n";
  content += `(Exportat pe: ${currentDate} la ${currentTime}) Tj\n`;

  content += "0 -20 Td\n";
  content += `(Total categorii: ${categories.length}) Tj\n`;

  //antetul tabelului
  content += "/F1 12 Tf\n";
  content += "0 -40 Td\n";
  content += "(ID    Nume) Tj\n";

  //adaugam fiecare categorie
  content += "/F1 10 Tf\n";
  let yOffset = -20;
  categories.forEach((category, index) => {
    if (index > 40) return;

    content += `0 ${yOffset} Td\n`;
    const line = `${category.id.toString().padEnd(6)} ${category.name.substring(
      0,
      30
    )}`;
    content += `(${line}) Tj\n`;
    yOffset = -15;
  });

  content += "ET\n"; //end text

  const contentLength = content.length;
  pdfData += `4 0 obj\n<<\n/Length ${contentLength}\n>>\nstream\n${content}\nendstream\nendobj\n\n`;

  //fontul Helvetica
  pdfData +=
    "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n\n";

  //tabelul de referinta pentru navigare in pdf
  pdfData += "xref\n0 6\n";
  pdfData += "0000000000 65535 f \n";
  pdfData += "0000000009 65535 n \n";
  pdfData += "0000000074 65535 n \n";
  pdfData += "0000000120 65535 n \n";
  pdfData += "0000000179 65535 n \n";
  pdfData += `0000000${(pdfData.length + 50)
    .toString()
    .padStart(6, "0")} 00000 n \n`;

  //trailer-ul documentului - metadatele finale
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

function handleFileSelect(event) {
  const file = event.target.files[0];
  if (!file) return;

  const fileExtension = file.name.split(".").pop().toLowerCase();

  if (fileExtension === "csv") {
    importFromCSV(file);
  } else if (fileExtension === "json") {
    importFromJSON(file);
  } else {
    alert("Format de fișier nesuportat! Folosiți doar CSV sau JSON.");
  }
}

function importFromCSV(file) {
  const reader = new FileReader();
  reader.onload = function (e) {
    try {
      const csv = e.target.result;
      const lines = csv.split("\n").filter((line) => line.trim() !== "");

      if (lines.length < 2) {
        alert("Fișierul CSV este gol sau nu conține date valide!");
        return;
      }

      // verifica daca prima linie are headers
      const firstLine = lines[0].toLowerCase();
      const hasHeaders =
        firstLine.includes("name") ||
        firstLine.includes("nume") ||
        firstLine.includes("traits");

      const dataLines = hasHeaders ? lines.slice(1) : lines;
      const categories = [];

      dataLines.forEach((line, index) => {
        const columns = parseCSVLine(line);

        if (columns.length > 0 && columns[0].trim() !== "") {
          const categoryName = columns[0].trim().replace(/^"(.*)"$/, "$1");

          if (categoryName) {
            // extrage traiturile din a doua coloana (daca exista)
            let traits = [];
            if (columns.length > 1 && columns[1].trim() !== "") {
              const traitsString = columns[1].trim().replace(/^"(.*)"$/, "$1");

              // traiturile pot fi separate prin , ; sau |
              if (traitsString) {
                traits = traitsString
                  .split(/[,;|]/)
                  .map((trait) => trait.trim())
                  .filter((trait) => trait.length > 0);
              }
            }

            categories.push({
              name: categoryName,
              traits: traits,
              status: "approved",
            });
          }
        }
      });

      if (categories.length === 0) {
        alert("Nu s-au găsit categorii valide în fișier!");
        return;
      }

      confirmAndImportCategories(categories, "CSV");
    } catch (error) {
      console.error("Eroare la parsarea CSV:", error);
      alert("Eroare la citirea fișierului CSV!");
    }
  };

  reader.readAsText(file);
}

function importFromJSON(file) {
  const reader = new FileReader();
  reader.onload = function (e) {
    try {
      const jsonData = JSON.parse(e.target.result);
      let categories = [];

      if (Array.isArray(jsonData)) {
        categories = jsonData
          .map((item) => {
            const name =
              item.name || item.nume || item.category || item.categorie;

            if (!name) {
              console.warn("Înregistrare ignorată - lipsește numele:", item);
              return null;
            }

            return {
              name: name.toString().trim(),
              traits: item.traits || [],
              status: "approved",
            };
          })
          .filter(Boolean); //sterge inregistrarile null
      } else if (jsonData.categories && Array.isArray(jsonData.categories)) {
        categories = jsonData.categories.map((item) => ({
          name: (item.name || item.nume).toString().trim(),
          traits: item.traits || [],
          status: "approved",
        }));
      } else {
        alert(
          'Formatul JSON nu este recunoscut! Folosiți un array de obiecte sau un obiect cu proprietatea "categories".'
        );
        return;
      }

      if (categories.length === 0) {
        alert("Nu s-au găsit categorii valide în fișierul JSON!");
        return;
      }

      confirmAndImportCategories(categories, "JSON");
    } catch (error) {
      console.error("Eroare la parsarea JSON:", error);
      alert(
        "Eroare la citirea fișierului JSON! Verificați formatul fișierului."
      );
    }
  };

  reader.readAsText(file);
}

function parseCSVLine(line) {
  const result = [];
  let current = "";
  let inQuotes = false;

  for (let i = 0; i < line.length; i++) {
    const char = line[i];

    if (char === '"') {
      inQuotes = !inQuotes;
    } else if (char === "," && !inQuotes) {
      result.push(current);
      current = "";
    } else {
      current += char;
    }
  }

  result.push(current);
  return result;
}

function confirmAndImportCategories(categories, fileType) {
  const message = `Ați selectat ${
    categories.length
  } categorii din fișierul ${fileType}.\n\nPrimele 5 categorii:\n${categories
    .slice(0, 5)
    .map((c) => `- ${c.name}`)
    .join("\n")}\n\nContinuați cu importul?`;

  if (confirm(message)) {
    performImport(categories);
  }
}

async function performImport(categories) {
  try {
    let successCount = 0;
    let errorCount = 0;
    const errors = [];

    for (let i = 0; i < categories.length; i++) {
      const category = categories[i];

      try {
        const response = await fetch(
          `http://localhost/IRI_Ballerina_Cappuccina/api/admin/categories/import`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "include",
            body: JSON.stringify(category),
          }
        );

        if (response.ok) {
          successCount++;
        } else {
          errorCount++;
          const errorData = await response.json();
          errors.push(
            `${category.name}: ${errorData.message || "Eroare necunoscută"}`
          );
        }
      } catch (error) {
        errorCount++;
        errors.push(`${category.name}: Eroare de conexiune`);
      }
    }

    let resultMessage = `Import finalizat!\n\nCategorii importate cu succes: ${successCount}\nErori: ${errorCount}`;

    if (errors.length > 0 && errors.length <= 5) {
      resultMessage += "\n\nErori:\n" + errors.join("\n");
    } else if (errors.length > 5) {
      resultMessage += "\n\nPrimele 5 erori:\n" + errors.slice(0, 5).join("\n");
    }

    alert(resultMessage);

    if (successCount > 0) {
      loadCategories();
    }
  } catch (error) {
    console.error("Eroare la import:", error);
    alert("Eroare generală la import!");
  }
}

function setupFileInputListener() {
  const fileInput = document.getElementById("importFile");
  if (fileInput) {
    fileInput.removeEventListener("change", handleFileSelect);
    fileInput.addEventListener("change", handleFileSelect);
  }
}

initializeTemplates();
loadCategories();
