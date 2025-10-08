async function incarcaPaginaDinQuery() {
  const contentDiv = document.getElementById("content");
  contentDiv.classList.remove("loaded");

  let path = window.location.pathname;
  let page = "categories";
  let isAdmin = false;

  const pathParts = path.split("/").filter(Boolean);
  const publicIndex = pathParts.indexOf("public");

  if (publicIndex !== -1) {
    if (pathParts[publicIndex + 1] === "admin") {
      const adminPage = pathParts[publicIndex + 2];
      page = "admin/" + (adminPage || "dashboard");
      isAdmin = true;
    } else if (pathParts[publicIndex + 1] === "entities" && pathParts.length >= publicIndex + 4) {
      page = "reviews";
    } else {
      page = pathParts[publicIndex + 1] || "categories";
    }
  }
  
  //incarcam html ul corespunzator url-ului
  try {
    const response = await fetch(
      `/IRI_Ballerina_Cappuccina/public/pages/${page}.html`,
      {
        credentials: "include",
      }
    );
    if (!response.ok) throw new Error("Pagina nu există.");

    const html = await response.text();
    contentDiv.innerHTML = html;

    //se incarca css-ul corespunzator
    const pageStylesheet = document.querySelector(`link[href*="${page}.css"]`);
    if (!pageStylesheet) {
      const cssLink = document.createElement("link");
      cssLink.rel = "stylesheet";
      cssLink.href = `/IRI_Ballerina_Cappuccina/public/css/${page}.css`;
      document.head.appendChild(cssLink);
    }
    
    //se incarca scriptul corespunzator
    await loadScript(`/IRI_Ballerina_Cappuccina/public/js/${page}.js`);

    requestAnimationFrame(() => {
      contentDiv.classList.add("loaded");
    });
  } catch (error) {
    contentDiv.innerHTML = `<h2>Eroare</h2><p>Pagina nu a putut fi încărcată.</p>`;
    console.error(error);
  }
}

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) {
      resolve();
      return;
    }

    const script = document.createElement("script");
    script.src = src;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error(`Script load error: ${src}`));
    document.body.appendChild(script);
  });
}

window.addEventListener("DOMContentLoaded", incarcaPaginaDinQuery);