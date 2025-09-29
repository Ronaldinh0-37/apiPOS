document.addEventListener('DOMContentLoaded', () => {

/* FILE SELECTORS (LOGO, EXCEL, SUNAT) */
const inputLogo = document.querySelector('#logo[type="file"]');
const logoInfo =
document.querySelector('#logo[type="file"] + .file-info') ||
document.querySelector('#logo_info');

if (inputLogo) {
inputLogo.addEventListener('change', function () {
if (this.files?.length) {
if (logoInfo) logoInfo.textContent = `Archivo seleccionado: ${this.files[0].name}`;
} else {
if (logoInfo) logoInfo.textContent = 'No se ha seleccionado ningún archivo';
}
});
}

/* FILE SELECTOR FOR EXCEL */
const inputExcel = document.querySelector('#archivo_excel[type="file"], #archivo-excel[type="file"]');
const nombreArchivoExcel =
document.querySelector('#archivo_excel[type="file"] + .file-info') ||
document.querySelector('#archivo-excel[type="file"] + .file-info') ||
document.querySelector('#archivo_excel_info');

if (inputExcel) {
inputExcel.addEventListener('change', function () {
if (this.files?.length) {
nombreArchivoExcel && (nombreArchivoExcel.textContent = `Archivo seleccionado: ${this.files[0].name}`);
} else {
nombreArchivoExcel && (nombreArchivoExcel.textContent = 'No se ha seleccionado ningún archivo');
}
});
}

/* FILE SELECTOR FOR SUNAT DOCUMENTS */
const inputSunat = document.querySelector('#archivo_sunat[type="file"], #archivo-sunat[type="file"], #documentos-sunat[type="file"]');
const nombreArchivoSunat =
document.querySelector('#archivo_sunat[type="file"] + .file-info') ||
document.querySelector('#archivo-sunat[type="file"] + .file-info') ||
document.querySelector('#documentos-sunat[type="file"] + .file-info') ||
document.querySelector('#archivo_sunat_info');

if (inputSunat) {
inputSunat.addEventListener('change', function () {
if (this.files?.length) {
if (this.files.length === 1) {
nombreArchivoSunat && (nombreArchivoSunat.textContent = `Archivo seleccionado: ${this.files[0].name}`);
} else {
nombreArchivoSunat && (nombreArchivoSunat.textContent = `${this.files.length} archivos seleccionados`);
}
} else {
nombreArchivoSunat && (nombreArchivoSunat.textContent = 'No se ha seleccionado ningún archivo');
}
});
}

/* NOTIFICATIONS */
const showNotification = () => {
const n = document.getElementById('requiredFieldsNotification');
if (!n) return;
n.style.display = 'block';
setTimeout(() => (n.style.display = 'none'), 5000);
};

const showEmailNotification = () => {
const n = document.getElementById('emailNotification');
if (!n) return;
n.style.display = 'block';
setTimeout(() => (n.style.display = 'none'), 5000);
};

const showSuccessNotification = () => {
const n = document.getElementById('successNotification');
if (!n) return;
n.style.display = 'block';
setTimeout(() => (n.style.display = 'none'), 5000);
};

const isValidEmail = (email) => email && email.includes('@') && email.includes('.');

/* STEP NAVIGATION */
function goto(n) {
document.querySelectorAll('.bullet').forEach(b => b.classList.toggle('active', Number(b.dataset.step) === n));
document.querySelectorAll('.step').forEach(s => (s.hidden = Number(s.dataset.step) !== n));
const registro = document.getElementById('registro');
if (registro) window.scrollTo({ top: registro.offsetTop - 80, behavior: 'smooth' });
}
window.goto = goto;

/* STEP VALIDATION */
function validateStep(step) {
const currentStep = document.querySelector(`.step[data-step="${step}"]`);
if (!currentStep) return;
const requiredFields = currentStep.querySelectorAll('.required');
let isValid = true;

requiredFields.forEach(field => {
const wrapper = field.closest('div') || field.parentElement;
const input = wrapper?.querySelector('input, select, textarea');
if (input && input.type !== 'checkbox' && !input.value.trim()) {
isValid = false;
input.style.borderColor = 'red';
} else if (input) {
input.style.borderColor = '';
}
});

/* ADDITIONAL RULES FOR STEP 3 */
if (step === 3) {
const terminos = document.getElementById('terminos_condiciones');
if (terminos && !terminos.checked) {
isValid = false;
terminos.style.outline = '2px solid red';
} else if (terminos) {
terminos.style.outline = '';
}

const emailInput = document.getElementById('email_crear_cuenta');
if (emailInput && emailInput.value.trim() && !isValidEmail(emailInput.value.trim())) {
isValid = false;
emailInput.style.borderColor = 'red';
}
}

if (!isValid) {
if (step === 3) {
const emailInput = document.getElementById('email_crear_cuenta');
if (emailInput && emailInput.value.trim() && !isValidEmail(emailInput.value.trim())) {
showEmailNotification();
} else {
showNotification();
}
} else {
showNotification();
}
return;
}

goto(step + 1);
}
window.validateStep = validateStep;

/* VALIDATE AND SUBMIT FULL FORM */
function validateAndSubmit() {
let isValid = true;

for (let i = 1; i <= 5; i++) {
const step = document.querySelector(`.step[data-step="${i}"]`);
if (!step) continue;
const requiredFields = step.querySelectorAll('.required');

requiredFields.forEach(field => {
const wrapper = field.closest('div') || field.parentElement;
const input = wrapper?.querySelector('input, select, textarea');
if (input && input.type !== 'checkbox' && !input.value.trim()) {
isValid = false;
input.style.borderColor = 'red';
}
});

if (i === 3) {
const terminos = document.getElementById('terminos_condiciones');
if (terminos && !terminos.checked) {
isValid = false;
terminos.style.outline = '2px solid red';
}
const emailInput = document.getElementById('email_crear_cuenta');
if (emailInput && emailInput.value.trim() && !isValidEmail(emailInput.value.trim())) {
isValid = false;
emailInput.style.borderColor = 'red';
}
}
}

if (!isValid) {
const emailInput = document.getElementById('email_crear_cuenta');
if (emailInput && emailInput.value.trim() && !isValidEmail(emailInput.value.trim())) {
showEmailNotification();
} else {
showNotification();
}
return;
}

showSuccessNotification();

}
window.validateAndSubmit = validateAndSubmit;

/* TOGGLE PASSWORD VISIBILITY */
const togglePasswordBtn = document.getElementById('togglePassword');
if (togglePasswordBtn) {
togglePasswordBtn.addEventListener('click', function () {
const passwordInput = document.getElementById('password_crear_cuenta');
if (!passwordInput) return;
const type = passwordInput.type === 'password' ? 'text' : 'password';
passwordInput.type = type;
this.classList.toggle('fa-eye');
this.classList.toggle('fa-eye-slash');
});
}

/* TERMS AND SUPPORT CHECKBOX INTERACTION */
const terminosCheckbox = document.getElementById('terminos_condiciones');
const soporteCheckbox = document.getElementById('soporte');
if (terminosCheckbox) {
terminosCheckbox.addEventListener('change', function () {
if (this.checked && soporteCheckbox) soporteCheckbox.checked = false;
});
}

/* EMAIL VALIDATION ON BLUR */
const emailBlurInput = document.getElementById('email_crear_cuenta');
if (emailBlurInput) {
emailBlurInput.addEventListener('blur', function () {
if (this.value.trim() && !isValidEmail(this.value.trim())) {
this.style.borderColor = 'red';
showEmailNotification();
} else {
this.style.borderColor = '';
}
});
}

/* REMEMBER LOGIN DATA (LOCAL STORAGE) */
const rememberCheckbox = document.getElementById('remember');
const emailInput = document.getElementById('email_crear_cuenta');
const passwordInput = document.getElementById('password_crear_cuenta');

/* LOAD SAVED DATA FROM LOCAL STORAGE */
if ((emailInput || passwordInput) && rememberCheckbox) {
const savedEmail = localStorage.getItem('savedEmail');
const savedPassword = localStorage.getItem('savedPassword');
if (savedEmail && emailInput) emailInput.value = savedEmail;
if (savedPassword && passwordInput) passwordInput.value = savedPassword;
if ((savedEmail || savedPassword) && rememberCheckbox) rememberCheckbox.checked = true;
}

/* SAVE OR DELETE DATA WHEN CHECKBOX CHANGES */
if (rememberCheckbox) {
rememberCheckbox.addEventListener('change', () => {
if (rememberCheckbox.checked) {
emailInput && localStorage.setItem('savedEmail', emailInput.value || '');
passwordInput && localStorage.setItem('savedPassword', passwordInput.value || '');
} else {
localStorage.removeItem('savedEmail');
localStorage.removeItem('savedPassword');
}
});

/* UPDATE SAVED DATA WHEN USER TYPES */
if (emailInput) {
emailInput.addEventListener('input', () => {
if (rememberCheckbox.checked) localStorage.setItem('savedEmail', emailInput.value || '');
});
}
if (passwordInput) {
passwordInput.addEventListener('input', () => {
if (rememberCheckbox.checked) localStorage.setItem('savedPassword', passwordInput.value || '');
});
}
}

});
