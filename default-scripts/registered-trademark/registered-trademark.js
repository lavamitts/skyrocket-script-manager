/**
 * @file Brand Trademark Enforcer
 * @description Automatically appends a registered trademark symbol (®) to specific brand terms
 * ("Sudometrics", "Sudopad", "Neuropad", "feet4life") throughout the document body.
 * The script skips execution on specific excluded pages and ignores non-visible
 * elements like scripts and styles. It ensures the trademark is only added if
 * not already present to avoid duplication.
 */
(function () {
  const body = document.body;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
  const textNodes = [];

  while (walker.nextNode()) {
    const node = walker.currentNode;
    const parent = node.parentNode;

    // Skip technical tags and existing superscript tags
    if (["SCRIPT", "STYLE", "NOSCRIPT", "SUP"].includes(parent.nodeName)) continue;

    const brandRegex = /\b(Sudometrics|Sudopad|Neuropad|feet4life|Feet4life)\b/g;

    if (brandRegex.test(node.nodeValue)) {
      // Logic to prevent duplication:
      // Check if the parent is already a span created by this script
      // or if the next sibling is already a trademark <sup>
      const nextSibling = node.nextSibling;
      const isAlreadyMarked =
        nextSibling &&
        nextSibling.nodeName === "SUP" &&
        (nextSibling.textContent === "®" || nextSibling.innerHTML.includes("&reg;"));

      const isInsideMarkedSpan = parent.nodeName === "SPAN" && parent.innerHTML.includes("<sup>");

      if (!isAlreadyMarked && !isInsideMarkedSpan) {
        textNodes.push(node);
      }
    }
  }

  // Process nodes
  textNodes.forEach((node) => {
    const parent = node.parentNode;
    const regex = /\b(Sudometrics|Sudopad|Neuropad|feet4life|Feet4life)\b/g;

    // Create the span wrapper as per your layout requirements
    const wrapperSpan = document.createElement("span");

    // Insert the HTML with the trademark
    const updatedHTML = node.nodeValue.replace(regex, "$1<sup>&reg;</sup>");
    wrapperSpan.innerHTML = updatedHTML;

    // Replace the original text node with the new span
    if (parent) {
      parent.replaceChild(wrapperSpan, node);
    }
  });
})();
