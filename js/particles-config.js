
particlesJS("particles-js", {
  particles: {
    number: {
      value: 75,
      density: { enable: true, value_area: 950 },
    },
    color: {
      value: ["#00b7ff", "#5ac8fa", "#3a7bd5", "#80ffea"],
    },
    shape: {
      type: "circle",
      stroke: { width: 0, color: "#000" },
    },
    opacity: {
      value: 0.35,
      random: true,
      anim: {
        enable: true,
        speed: 0.8,
        opacity_min: 0.15,
        sync: false,
      },
    },
    size: {
      value: 2.6,
      random: true,
      anim: {
        enable: true,
        speed: 2.5,
        size_min: 0.4,
        sync: false,
      },
    },
    line_linked: {
      enable: true,
      distance: 140,
      color: "#00e5ff",
      opacity: 0.25,
      width: 0.9,
    },
    move: {
      enable: true,
      speed: 1.2,
      direction: "none",
      random: true,
      straight: false,
      out_mode: "out",
      attract: { enable: false },
    },
  },
  interactivity: {
    detect_on: "canvas",
    events: {
      onhover: { enable: true, mode: "grab" },
      onclick: { enable: true, mode: "repulse" },
      resize: true,
    },
    modes: {
      grab: {
        distance: 180,
        line_linked: { opacity: 0.45 },
      },
      repulse: { distance: 200, duration: 0.4 },
    },
  },
  retina_detect: true,
});

