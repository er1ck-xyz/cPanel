
particlesJS("particles-js", {
  particles: {
    number: {
      value: 60,
      density: { enable: true, value_area: 1000 },
    },
    color: { value: ["#00b7ff", "#3a7bd5", "#4cc9f0"] },
    shape: { type: "circle" },
    opacity: {
      value: 0.25,
      random: true,
      anim: { enable: true, speed: 0.6, opacity_min: 0.05 },
    },
    size: {
      value: 2.3,
      random: true,
      anim: { enable: true, speed: 2, size_min: 0.2 },
    },
    line_linked: {
      enable: true,
      distance: 130,
      color: "#00b7ff",
      opacity: 0.2,
      width: 0.7,
    },
    move: { enable: true, speed: 1.1, random: true, out_mode: "out" },
  },
  interactivity: {
    detect_on: "canvas",
    events: { onhover: { enable: true, mode: "grab" } },
    modes: { grab: { distance: 160, line_linked: { opacity: 0.3 } } },
  },
  retina_detect: true,
});

