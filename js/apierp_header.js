
//HEADER TOOGLE
const headerToggleBtn = document.querySelector('.header-toggle');
function headerToggle(){
document.querySelector('#header').classList.toggle('header-show');
document.querySelector('#topbar').classList.toggle('topbar-show');
}
headerToggleBtn.addEventListener('click', headerToggle);

//DOMCONTENTLOADED
document.addEventListener("DOMContentLoaded", function(){
// Selecciona todos los enlaces de los elementos con submenú
const submenuLinks = document.querySelectorAll(".navmenu li.has-submenu > a");

submenuLinks.forEach(function(link) {
link.addEventListener("click", function(event) {
event.preventDefault(); // Evita la acción por defecto del enlace

// Obtiene el li padre del enlace clicado
const parentLi = this.parentElement;

// Elimina la clase 'expanded' de todos los li con submenú, excepto el actual
document.querySelectorAll(".navmenu li.has-submenu").forEach(function(item) {
if (item !== parentLi) {
item.classList.remove("expanded");
}
});

// Alterna la clase 'expanded' en el li actual
parentLi.classList.toggle("expanded");
});
});
});