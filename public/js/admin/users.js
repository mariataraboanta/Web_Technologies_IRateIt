let currentUser = null;
let users = [];
let userTemplate;

Handlebars.registerHelper("formatDate", function (dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("ro-RO");
});

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

async function loadUsers() {
  const hasAccess = await checkAdminAccess();
  if (!hasAccess) return;

  try {
    const response = await fetch(
      "http://localhost/IRI_Ballerina_Cappuccina/api/users",
      {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (response.status === 403) {
      displayError("Nu aveți permisiuni pentru a accesa lista de utilizatori");
      return;
    }

    users = await response.json();

    //compilam templateul
    if (!userTemplate) {
      const source = document.getElementById("user-template").innerHTML;
      userTemplate = Handlebars.compile(source);
    }

    displayUsers(users);
  } catch (error) {
    console.error("Eroare la încărcarea utilizatorilor:", error);
    displayError("Nu s-au putut încărca utilizatorii");
  }
}

function displayUsers(usersToDisplay) {
  const tbody = document.getElementById("usersTableBody");

  //folosim templateul sa generam userii
  tbody.innerHTML = userTemplate({ users: usersToDisplay });

  document.querySelectorAll(".delete-user-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const userId = this.getAttribute("data-id");
      deleteUser(userId);
    });
  });
}

function displayError(message) {
  const tbody = document.getElementById("usersTableBody");
  tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 20px; color: red;">${message}</td></tr>`;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("ro-RO");
}

document.getElementById("search").addEventListener("input", function (e) {
  const searchTerm = e.target.value.toLowerCase();
  const filteredUsers = users.filter(
    (user) =>
      user.username.toLowerCase().includes(searchTerm) ||
      user.email.toLowerCase().includes(searchTerm)
  );
  displayUsers(filteredUsers);
});

async function deleteUser(userId) {
  if (!confirm("Sigur vrei să ștergi acest utilizator?")) return;

  try {
    const response = await fetch(
      `http://localhost/IRI_Ballerina_Cappuccina/api/users/${userId}`,
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
      alert("Nu aveți permisiuni pentru a șterge utilizatori");
      return;
    }

    if (response.ok) {
      loadUsers();
      alert("Utilizator șters cu succes!");
    } else {
      alert("Eroare la ștergerea utilizatorului");
    }
  } catch (error) {
    console.error("Eroare:", error);
    alert("Eroare la ștergerea utilizatorului");
  }
}

function exportUsers() {
  const format = document.getElementById("exportFormat").value;

  if (users.length === 0) {
    alert("Nu există utilizatori de exportat!");
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
  const headers = ["ID", "Nume", "Email", "Data Creare"];
  const csvContent = [
    headers.join(","),
    ...users.map((user) =>
      [
        user.id,
        `"${user.username}"`,
        `"${user.email}"`,
        `"${formatDate(user.created_at)}"`,
      ].join(",")
    ),
  ].join("\n");

  downloadFile(csvContent, "utilizatori.csv", "text/csv");
}

function exportToJSON() {
  const exportData = users.map((user) => ({
    id: user.id,
    nume: user.username,
    email: user.email,
    data_creare: formatDate(user.created_at),
  }));

  const jsonContent = JSON.stringify(exportData, null, 2);
  downloadFile(jsonContent, "utilizatori.json", "application/json");
}

function exportToPDF() {
  const pdfContent = generatePDFContent();
  const blob = new Blob([pdfContent], { type: "application/pdf" });
  downloadFile(blob, "utilizatori.pdf", "application/pdf");
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
  content += "(Lista Utilizatori) Tj\n";

  content += "/F1 10 Tf\n";
  content += "0 -30 Td\n";
  content += `(Exportat pe: ${currentDate} la ${currentTime}) Tj\n`;

  content += "0 -20 Td\n";
  content += `(Total utilizatori: ${users.length}) Tj\n`;

  //Header tabel
  content += "/F1 12 Tf\n";
  content += "0 -40 Td\n";
  content +=
    "(ID    Nume                    Email                           Data) Tj\n";

  //Date utilizatori
  content += "/F1 10 Tf\n";
  let yOffset = -20;
  users.forEach((user, index) => {
    if (index > 30) return;

    content += `0 ${yOffset} Td\n`;
    const line = `${user.id.toString().padEnd(6)} ${user.username
      .substring(0, 20)
      .padEnd(22)} ${user.email.substring(0, 30).padEnd(32)} ${formatDate(
      user.created_at
    )}`;
    content += `(${line}) Tj\n`;
    yOffset = -15;
  });

  content += "ET\n";

  const contentLength = content.length;
  pdfData += `4 0 obj\n<<\n/Length ${contentLength}\n>>\nstream\n${content}\nendstream\nendobj\n\n`;

  //Font
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

loadUsers();
