document.addEventListener("DOMContentLoaded", () => {
  const senhaInput = document.getElementById("senha");

  
  const wrapper = document.createElement("div");
  wrapper.className = "password-strength-wrapper hidden"; 

  
  const bar = document.createElement("div");
  bar.className = "password-bar";
  const texto = document.createElement("span");
  texto.className = "password-text";

  wrapper.appendChild(bar);
  wrapper.appendChild(texto);
  senhaInput.parentNode.insertAdjacentElement("afterend", wrapper);

  
  const render = (senha) => {
    if (!senha) {
      
      wrapper.classList.add("hidden");
      bar.style.width = "0%";
      bar.className = "password-bar";
      texto.textContent = "";
      return;
    }

    wrapper.classList.remove("hidden");
    const forca = avaliarSenha(senha);
    bar.style.width = forca.percent + "%";
    bar.className = `password-bar ${forca.cor}`;
    texto.textContent = forca.texto;
    texto.style.color = forca.textColor;
  };

  senhaInput.addEventListener("input", () => render(senhaInput.value));
  render(senhaInput.value); 
});

function avaliarSenha(senha) {
  let pontuacao = 0;

  if (senha.length >= 8) pontuacao++;
  if (/[A-Z]/.test(senha)) pontuacao++;
  if (/[a-z]/.test(senha)) pontuacao++;
  if (/[0-9]/.test(senha)) pontuacao++;
  if (/[^A-Za-z0-9]/.test(senha)) pontuacao++;

  if (senha.length < 4)
    return {
      texto: "Muito fraca",
      cor: "fraca",
      percent: 20,
      textColor: "#ff4d4d",
    };
  if (pontuacao <= 2)
    return { texto: "Fraca", cor: "fraca", percent: 40, textColor: "#ff4d4d" };
  if (pontuacao === 3)
    return { texto: "Média", cor: "media", percent: 60, textColor: "#ffa500" };
  if (pontuacao === 4)
    return { texto: "Forte", cor: "forte", percent: 80, textColor: "#00c851" };
  return {
    texto: "Muito forte",
    cor: "muito-forte",
    percent: 100,
    textColor: "#00e5ff",
  };
}

