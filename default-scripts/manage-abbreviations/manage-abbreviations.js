/**
 * @file Dynamic Multi-Regex Abbreviation Expander
 * @description Automatically wraps occurrences of multiple medical abbreviations
 * with HTML <abbr> tags containing their respective English titles using a TreeWalker.
 */

document.addEventListener("DOMContentLoaded", function () {
  const body = document.body;

  // Define any number of regex replacement rules here
  const replacementRules = [
    {
      // Rule 1: DPN, NDP, NPD
      regex: /\b(DPN|NDP|NPD)(?=[.\s:\)])/g,
      title: "Diabetic peripheral neuropathy",
    },
    {
      // Rule 2: Example of adding another abbreviation (e.g., CKD)
      regex: /\b(CKD)(?=[.\s:\)])/g,
      title: "Chronic kidney disease",
    },
    // Add as many rules as needed here...
  ];

  /**
   * Helper function to apply multiple regex rules to a text string in a single safe pass.
   * Prevents overlapping matches and HTML corruption.
   */
  function applyReplacementRules(text, rules) {
    const matches = [];

    // Collect all matches from all rules with their start/end indices
    rules.forEach((rule) => {
      // Ensure the regex has the global flag enabled
      const flags = rule.regex.flags.includes("g") ? rule.regex.flags : rule.regex.flags + "g";
      const regex = new RegExp(rule.regex.source, flags);

      let match;
      while ((match = regex.exec(text)) !== null) {
        matches.push({
          start: match.index,
          end: match.index + match[0].length,
          replacement: `<abbr title="${rule.title}">${match[0]}</abbr>`,
        });
      }
    });

    if (matches.length === 0) return text;

    // Sort matches by their starting index
    matches.sort((a, b) => a.start - b.start);

    // Filter out overlapping matches (prioritizing the first match found)
    const filteredMatches = [];
    let lastEnd = 0;
    for (const m of matches) {
      if (m.start >= lastEnd) {
        filteredMatches.push(m);
        lastEnd = m.end;
      }
    }

    // Reconstruct the string with replacements
    let result = "";
    let currentIndex = 0;
    for (const m of filteredMatches) {
      result += text.slice(currentIndex, m.start);
      result += m.replacement;
      currentIndex = m.end;
    }
    result += text.slice(currentIndex);

    return result;
  }

  // Efficiently navigate the DOM tree looking only for text content
  const walk = document.createTreeWalker(
    body,
    NodeFilter.SHOW_TEXT,
    {
      acceptNode: function (node) {
        const parent = node.parentNode.tagName;
        // Avoid modifying code, styles, or already processed abbreviation tags
        if (["SCRIPT", "STYLE", "ABBR", "TEXTAREA"].includes(parent)) {
          return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      },
    },
    false,
  );

  const nodesToReplace = [];
  let currentNode;

  // Collect text nodes that match ANY of the rules
  while ((currentNode = walk.nextNode())) {
    const hasMatch = replacementRules.some((rule) => {
      // Reset regex lastIndex just in case
      rule.regex.lastIndex = 0;
      return rule.regex.test(currentNode.nodeValue);
    });

    if (hasMatch) {
      nodesToReplace.push(currentNode);
    }
  }

  // Process each identified text node and inject the <abbr> elements
  nodesToReplace.forEach((node) => {
    const parent = node.parentNode;
    const content = node.nodeValue;

    // Use a span as a temporary container to parse the new HTML string
    const tempContainer = document.createElement("span");

    // Apply all replacement rules safely
    tempContainer.innerHTML = applyReplacementRules(content, replacementRules);

    // Move the new nodes from the container back into the actual document
    while (tempContainer.firstChild) {
      parent.insertBefore(tempContainer.firstChild, node);
    }
    // Remove the original plain text node
    parent.removeChild(node);
  });
});
