import './bootstrap.js';
import './styles/app.css';

document.addEventListener('DOMContentLoaded', function () {
    const toggleButtons = document.querySelectorAll('.toggle-password');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const wrapper = this.parentElement;
            const passwordInput = wrapper.querySelector('input[type="password"], input[type="text"]');

            if (!passwordInput) {
                return;
            }

            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const icon = this.querySelector('i');
            if (icon) {
                if (type === 'text') {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                this.textContent = type === 'text' ? '🙈' : '👁️';
            }
        });
    });
});