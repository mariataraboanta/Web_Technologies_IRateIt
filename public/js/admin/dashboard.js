let dashboardStats = [];
let topEntitiesData = { items: [] };
let lowEntitiesData = { items: [] };
let categoriesData = [];
let currentUser = null;

let statsTemplate;
let entityListTemplate;
let categoryTemplate;

const elements = {
  "stats-grid": document.getElementById("stats-grid"),
  "stats-loading": document.getElementById("stats-loading"),
  "top-entities": document.getElementById("top-entities"),
  "low-entities": document.getElementById("low-entities"),
  "category-grid": document.getElementById("category-grid"),
};

Handlebars.registerHelper("getName", function (entity) {
  return entity.name || entity.entity_name || "Necunoscut";
});

Handlebars.registerHelper("getCategory", function (entity) {
  return entity.category || entity.entity_category || "N/A";
});

Handlebars.registerHelper("getReviews", function (entity) {
  return entity.total_traits || 0;
});

Handlebars.registerHelper("getRating", function (entity) {
  if (entity.detestability_percentage !== undefined) {
    return entity.detestability_percentage + "% detestabil";
  } else if (entity.desirability_percentage !== undefined) {
    return entity.desirability_percentage + "% pozitiv";
  }
  return entity.rating || entity.avg_rating || "N/A";
});

Handlebars.registerHelper("getTraitsInfo", function (entity) {
  if (
    entity.positive_traits_count !== undefined &&
    entity.negative_traits_count !== undefined
  ) {
    return (
      entity.positive_traits_count +
      " pozitive, " +
      entity.negative_traits_count +
      " negative"
    );
  }
  return "";
});

function compileTemplates() {
  if (!statsTemplate) {
    const source = document.getElementById("stats-template").innerHTML;
    statsTemplate = Handlebars.compile(source);
  }

  if (!entityListTemplate) {
    const source = document.getElementById("entity-list-template").innerHTML;
    entityListTemplate = Handlebars.compile(source);
  }

  if (!categoryTemplate) {
    const source = document.getElementById("category-template").innerHTML;
    categoryTemplate = Handlebars.compile(source);
  }
}

async function checkAdminAccess() {
  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/auth/status`,
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

function handleError(error, location = "general") {
  console.error(`Eroare la ${location}:`, error);
  const errorDiv = document.getElementById("error-message") || createErrorDiv();
  errorDiv.textContent = `A apărut o eroare la ${location}: ${error.message}`;

  const loadingEl = document.getElementById("stats-loading");
  if (loadingEl) {
    loadingEl.style.display = "none";
  }
}

function createErrorDiv() {
  const div = document.createElement("div");
  div.id = "error-message";
  div.style.color = "red";
  div.style.margin = "1em 0";
  div.style.padding = "1em";
  div.style.border = "1px solid red";
  div.style.borderRadius = "4px";
  div.style.backgroundColor = "#ffebee";
  document.body.prepend(div);
  return div;
}

async function loadDashboardData() {
  const hasAccess = await checkAdminAccess();
  if (!hasAccess) return;

  compileTemplates();
  fetchStats();
  fetchTopEntities();
  fetchLowEntities();
  fetchCategories();
}

function fetchStats() {
  const loadingEl = document.getElementById("stats-loading");
  const container = document.getElementById("stats-grid");

  if (loadingEl) {
    loadingEl.style.display = "block";
  }

  const url = `http://localhost/IRI_Ballerina_Cappuccina/api/admin/stats`;

  fetch(url, {
    credentials: "include",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((res) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      return res.json();
    })
    .then((stats) => {
      dashboardStats = stats;

      if (loadingEl) {
        loadingEl.style.display = "none";
      }

      if (!container) {
        return;
      }

      //folosim templateul pentru statistici
      container.innerHTML = statsTemplate({ stats: dashboardStats });
    })
    .catch((err) => {
      console.error("Fetch stats error:", err);
      console.error("Error details:", {
        name: err.name,
        message: err.message,
        stack: err.stack,
      });

      handleError(err, "statistici");

      if (loadingEl) {
        loadingEl.style.display = "none";
      }

      if (container) {
        container.innerHTML =
          '<p style="color: red;">Eroare la încărcarea statisticilor.</p>';
      }
    });
}

function fetchTopEntities() {
  fetch(
    `http://localhost/IRI_Ballerina_Cappuccina/api/rss-json?type=least_detestable`,
    {
      credentials: "include",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    }
  )
    .then((res) => {
      if (!res.ok) throw new Error(`status ${res.status}`);
      return res.json();
    })
    .then((data) => {
      topEntitiesData = data;

      const list = document.getElementById("top-entities");

      //folosim templateul pentru entitati
      list.innerHTML = entityListTemplate({
        entities: topEntitiesData.items || [],
        emptyMessage: "Nu există entități disponibile.",
      });
    })
    .catch((err) => {
      console.error("Fetch top entities error:", err);
      handleError(err, "top entități");
    });
}

function fetchLowEntities() {
  fetch(
    `http://localhost/IRI_Ballerina_Cappuccina/api/rss-json?type=most_detestable`,
    {
      credentials: "include",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    }
  )
    .then((res) => {
      if (!res.ok) throw new Error(`status ${res.status}`);
      return res.json();
    })
    .then((data) => {
      lowEntitiesData = data;

      const list = document.getElementById("low-entities");

      //folosim templateul pentru entitati
      list.innerHTML = entityListTemplate({
        entities: lowEntitiesData.items || [],
        emptyMessage: "Nu există entități pozitive.",
      });
    })
    .catch((err) => {
      console.error("Fetch low entities error:", err);
      handleError(err, "entități pozitive");
    });
}

function fetchCategories() {
  fetch(`http://localhost/IRI_Ballerina_Cappuccina/api/categories-stats`, {
    credentials: "include",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((res) => {
      if (!res.ok) throw new Error(`status ${res.status}`);
      return res.json();
    })
    .then((categories) => {
      categoriesData = categories;

      const container = document.getElementById("category-grid");

      container.innerHTML = categoryTemplate({ categories: categoriesData });
    })
    .catch((err) => {
      console.error("Fetch categories error:", err);
      handleError(err, "categorii");
    });
}

function exportDashboardData() {
  const format = document.getElementById("exportFormat").value;

  if (
    dashboardStats.length === 0 &&
    (!topEntitiesData.items || topEntitiesData.items.length === 0) &&
    (!lowEntitiesData.items || lowEntitiesData.items.length === 0) &&
    categoriesData.length === 0
  ) {
    alert("Nu există date de exportat!");
    return;
  }

  switch (format) {
    case "csv":
      exportDashboardToCSV();
      break;
    case "json":
      exportDashboardToJSON();
      break;
    case "pdf":
      exportDashboardToPDF();
      break;
    default:
      alert("Format de export invalid!");
  }
}

function exportDashboardToCSV() {
  let csvContent = "";

  if (dashboardStats.length > 0) {
    csvContent += "STATISTICI GENERALE\n";
    csvContent += "Metrica,Valoare\n";
    dashboardStats.forEach((stat) => {
      csvContent += `"${stat.label}","${stat.value}"\n`;
    });
    csvContent += "\n";
  }

  if (topEntitiesData.items && topEntitiesData.items.length > 0) {
    csvContent += "ENTITĂȚI POZITIVE\n";
    csvContent +=
      "Nume,Categorie,Trăsături Pozitive,Trăsături Negative,Total Trăsături,Procent Dezirabilitate\n";
    topEntitiesData.items.forEach((entity) => {
      csvContent += `"${entity.name}","${entity.category}","${entity.positive_traits_count}","${entity.negative_traits_count}","${entity.total_traits}","${entity.desirability_percentage}%"\n`;
    });
    csvContent += "\n";
  }

  if (lowEntitiesData.items && lowEntitiesData.items.length > 0) {
    csvContent += "ENTITĂȚI NEGATIVE\n";
    csvContent +=
      "Nume,Categorie,Trăsături Pozitive,Trăsături Negative,Total Trăsături,Procent Detestabilitate\n";
    lowEntitiesData.items.forEach((entity) => {
      csvContent += `"${entity.name}","${entity.category}","${entity.positive_traits_count}","${entity.negative_traits_count}","${entity.total_traits}","${entity.detestability_percentage}%"\n`;
    });
    csvContent += "\n";
  }

  if (categoriesData.length > 0) {
    csvContent += "STATISTICI CATEGORII\n";
    csvContent += "Nume,Număr Entități,Evaluări,Rating Mediu\n";
    categoriesData.forEach((category) => {
      csvContent += `"${category.name}","${category.count}","${
        category.reviews
      }","${category.avg_rating || "N/A"}"\n`;
    });
  }

  downloadFile(csvContent, "dashboard_export.csv", "text/csv");
}

function exportDashboardToJSON() {
  const exportData = {
    export_date: new Date().toISOString(),
    statistici_generale: dashboardStats,
    entitati_pozitive: topEntitiesData,
    entitati_detestabile: lowEntitiesData,
    categorii: categoriesData,
  };

  const jsonContent = JSON.stringify(exportData, null, 2);
  downloadFile(jsonContent, "dashboard_export.json", "application/json");
}

function exportDashboardToPDF() {
  const pdfContent = generateDashboardPDFContent();
  const blob = new Blob([pdfContent], { type: "application/pdf" });
  downloadFile(blob, "dashboard_export.pdf", "application/pdf");
}

function generateDashboardPDFContent() {
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
  content += "(Raport Dashboard) Tj\n";

  content += "/F1 10 Tf\n";
  content += "0 -30 Td\n";
  content += `(Exportat pe: ${currentDate} la ${currentTime}) Tj\n`;

  if (dashboardStats.length > 0) {
    content += "/F1 14 Tf\n";
    content += "0 -30 Td\n";
    content += "(Statistici Generale) Tj\n";

    content += "/F1 10 Tf\n";
    let yOffset = -20;
    dashboardStats.forEach((stat) => {
      content += `0 ${yOffset} Td\n`;
      content += `(${stat.label}: ${stat.value}) Tj\n`;
      yOffset = -15;
    });
  }

  if (topEntitiesData.items && topEntitiesData.items.length > 0) {
    content += "/F1 14 Tf\n";
    content += "0 -30 Td\n";
    content += "(Entitati Pozitive) Tj\n";

    content += "/F1 10 Tf\n";
    let yOffset = -20;
    topEntitiesData.items.slice(0, 5).forEach((entity) => {
      content += `0 ${yOffset} Td\n`;
      content += `(${entity.name} - ${entity.desirability_percentage}% pozitiv) Tj\n`;
      yOffset = -15;
    });
  }

  if (lowEntitiesData.items && lowEntitiesData.items.length > 0) {
    content += "/F1 14 Tf\n";
    content += "0 -30 Td\n";
    content += "(Entitati Detestabile) Tj\n";

    content += "/F1 10 Tf\n";
    let yOffset = -20;
    lowEntitiesData.items.slice(0, 5).forEach((entity) => {
      content += `0 ${yOffset} Td\n`;
      content += `(${entity.name} - ${entity.detestability_percentage}% destestabil) Tj\n`;
      yOffset = -15;
    });
  }

  if (categoriesData.length > 0) {
    content += "/F1 14 Tf\n";
    content += "0 -30 Td\n";
    content += "(Categorii) Tj\n";

    content += "/F1 10 Tf\n";
    let yOffset = -20;
    categoriesData.slice(0, 5).forEach((category) => {
      content += `0 ${yOffset} Td\n`;
      content += `(${category.name}: ${category.count} entitati) Tj\n`;
      yOffset = -15;
    });
  }

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

loadDashboardData();