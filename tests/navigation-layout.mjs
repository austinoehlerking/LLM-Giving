/** Inspect painted navigation text as well as link boxes; document width alone misses overlap. */
export async function navigationGeometry(page) {
  return page.locator('.floating-header a').evaluateAll(anchors => {
    const links = anchors.map(anchor => {
      const box = anchor.getBoundingClientRect();
      const walker = document.createTreeWalker(anchor, NodeFilter.SHOW_TEXT);
      const textRects = [];
      while (walker.nextNode()) {
        if (!walker.currentNode.textContent.trim()) continue;
        const range = document.createRange();
        range.selectNodeContents(walker.currentNode);
        for (const rect of range.getClientRects()) if (rect.width && rect.height) {
          textRects.push({x: rect.x, y: rect.y, width: rect.width, height: rect.height});
        }
      }
      const textFits = textRects.every(rect => rect.x >= box.x - 1 && rect.y >= box.y - 1
        && rect.x + rect.width <= box.right + 1 && rect.y + rect.height <= box.bottom + 1);
      const uncovered = textRects.every(rect => [0.1, 0.5, 0.9].every(fraction => {
        const hit = document.elementFromPoint(rect.x + rect.width * fraction, rect.y + rect.height / 2);
        return !!hit && (hit === anchor || anchor.contains(hit));
      }));
      return {text: anchor.textContent.trim(), x: box.x, y: box.y, width: box.width, height: box.height, textFits, uncovered, textRects};
    });
    const overlaps = [];
    for (let i = 0; i < links.length; i++) for (let j = i + 1; j < links.length; j++) {
      const a = links[i], b = links[j];
      if (Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x) > 1
        && Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y) > 1) {
        overlaps.push([a.text, b.text]);
      }
    }
    return {links, overlaps};
  });
}
