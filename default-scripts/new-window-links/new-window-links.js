document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll('a[target="_blank"]').forEach(function (link) {
    // Skip links already processed, and image/SVG links
    if (link.classList.contains("ijs-new-window")) return;
    if (link.querySelector("img, svg")) return;

    link.classList.add("ijs-new-window");

    var sr = document.createElement("span");
    sr.className = "ijs-screen-reader-text";
    sr.textContent = " (opens in a new tab)";
    link.appendChild(sr);
  });
});
