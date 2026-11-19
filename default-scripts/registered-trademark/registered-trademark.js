document.addEventListener("DOMContentLoaded", function () {
  const selectors = "h1, h2, h3, h4, p, li, th, td, span";

  document.querySelectorAll(selectors).forEach(function (element) {
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);

    const textNodes = [];

    while (walker.nextNode()) {
      textNodes.push(walker.currentNode);
    }

    textNodes.forEach(function (textNode) {
      const text = textNode.nodeValue;

      // Sudopad, Neuropad or Sudometrics followed by space, full stop, comma, semicolon,
      // or end of text.
      const regex = /(?:Sudopad|Neuropad|Sudometrics)(?= |[.,;]|$)/g;

      if (!regex.test(text)) {
        return;
      }

      const fragment = document.createDocumentFragment();
      let lastIndex = 0;

      text.replace(regex, function (match, offset) {
        // Don't add ® if the next DOM element is already a <sup>
        const nextSibling = textNode.nextSibling;

        if (
          offset === text.length - match.length &&
          nextSibling &&
          nextSibling.nodeType === Node.ELEMENT_NODE &&
          nextSibling.tagName.toLowerCase() === "sup"
        ) {
          return match;
        }

        fragment.appendChild(document.createTextNode(text.slice(lastIndex, offset + match.length)));

        const sup = document.createElement("sup");
        sup.textContent = "®";
        fragment.appendChild(sup);

        lastIndex = offset + match.length;

        return match;
      });

      fragment.appendChild(document.createTextNode(text.slice(lastIndex)));

      textNode.parentNode.replaceChild(fragment, textNode);
    });
  });
});
