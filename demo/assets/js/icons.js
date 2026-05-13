/* Utility for creating SVG icons */
const iconSVG = (d, size = 18, sw = 1.75) => {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', sw);
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  
  if (Array.isArray(d)) {
    d.forEach(path => {
      const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      p.setAttribute('d', path);
      svg.appendChild(p);
    });
  } else {
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', d);
    svg.appendChild(p);
  }
  return svg;
};

const Icons = {
  cloud: () => iconSVG("M17.5 19a4.5 4.5 0 0 0 0-9c-.5-3-3-5-6-5a6 6 0 0 0-6 6c0 .5.05 1 .15 1.5A4 4 0 0 0 6 19h11.5z"),
  home: () => iconSVG(["M3 11l9-8 9 8","M5 9.5V21h14V9.5"]),
  folder: () => iconSVG("M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"),
  image: () => iconSVG(["M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5z","M3 16l5-5 5 5","M14 14l3-3 4 4","M9.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"]),
  share: () => iconSVG(["M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M8.6 13.5l6.8 4","M15.4 6.5l-6.8 4"]),
  star: () => iconSVG("M12 3l2.7 5.5 6 .9-4.4 4.2 1 6-5.4-2.8L6.7 19.6l1-6L3.3 9.4l6-.9L12 3z"),
  trash: () => iconSVG(["M3 6h18","M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2","M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6","M10 11v6","M14 11v6"]),
  upload: () => iconSVG(["M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4","M17 8l-5-5-5 5","M12 3v12"]),
  download: () => iconSVG(["M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4","M7 10l5 5 5-5","M12 15V3"]),
  search: () => iconSVG(["M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z","M21 21l-4.3-4.3"]),
  plus: () => iconSVG(["M12 5v14","M5 12h14"]),
  more: () => iconSVG(["M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z","M19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z","M5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"]),
  sun: () => iconSVG(["M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z","M12 1v2","M12 21v2","M4.2 4.2l1.5 1.5","M18.3 18.3l1.5 1.5","M1 12h2","M21 12h2","M4.2 19.8l1.5-1.5","M18.3 5.7l1.5-1.5"]),
  moon: () => iconSVG("M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"),
  bell: () => iconSVG(["M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9","M13.7 21a2 2 0 0 1-3.4 0"]),
  settings: () => iconSVG(["M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"]),
  link: () => iconSVG(["M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1","M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"]),
  eye: () => iconSVG(["M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z","M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"]),
  lock: () => iconSVG(["M5 11h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1z","M8 11V7a4 4 0 0 1 8 0v4"]),
  check: () => iconSVG("M5 13l4 4L19 7"),
  x: () => iconSVG(["M18 6L6 18","M6 6l12 12"]),
  chevR: () => iconSVG("M9 6l6 6-6 6"),
  chevL: () => iconSVG("M15 6l-6 6 6 6"),
  chevD: () => iconSVG("M6 9l6 6 6-6"),
  copy: () => iconSVG(["M9 9h11a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V11a2 2 0 0 1 0-2z","M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"]),
  hdd: () => iconSVG(["M22 12H2","M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z","M6 16h.01","M10 16h.01"]),
  cpu: () => iconSVG(["M4 4h16v16H4z","M9 9h6v6H9z","M9 1v3","M15 1v3","M9 20v3","M15 20v3","M20 9h3","M20 14h3","M1 9h3","M1 14h3"]),
};

window.Icons = Icons;
window.iconSVG = iconSVG;
