{{--password script--}}

<script>
    const passwordInput = document.getElementById("password");
    const helpBox = document.getElementById("passwordHelp");
    const infoIcon = document.getElementById("passwordInfoIcon");

    if (!passwordInput || !helpBox) {
        // Required elements are missing on this page.
        // Exit early to avoid breaking other scripts.
        console.warn("Password helpers skipped: missing #password or #passwordHelp.");
    } else {

    passwordInput.addEventListener("input", function () {
        if (this.value.length === 0) {
            document.querySelector(".password-toggle-icon").style.top = "45%";
        } else {
            document.querySelector(".password-toggle-icon").style.top = "20%";
        }
    });

    const message = "Password must contain at least 8 characters, including uppercase, lowercase, number, and one special character (@, #, $).";


    // PASSWORD VALIDATION REGEX
    function isValidPassword(password) {
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$]).{8,}$/;
        return regex.test(password);
    }

    // AUTO SHOW / HIDE WHEN TYPING
    passwordInput.addEventListener("input", function () {
        const val = this.value;

        if (val.length === 0) {
            helpBox.classList.add("d-none");
            helpBox.innerHTML = "";
            return;
        }

        if (isValidPassword(val)) {
            helpBox.classList.add("d-none");
            helpBox.innerHTML = "";
        } else {
            helpBox.innerHTML = message;
            helpBox.classList.remove("d-none");
        }
    });

    // MANUAL TOGGLE WITH ICON (kept same)
    if (infoIcon) {
        infoIcon.addEventListener("click", function () {
            if (helpBox.classList.contains("d-none")) {
                helpBox.innerHTML = message;
                helpBox.classList.remove("d-none");

                this.classList.remove("text-primary");
                this.classList.add("text-danger");

            } else {
                helpBox.innerHTML = "";
                helpBox.classList.add("d-none");

                this.classList.add("text-primary");
                this.classList.remove("text-danger");
            }
        });
    }
    }


</script>

{{--confirm password script--}}
<script>
    const confirmInput = document.getElementById("confirmPassword");
    const confirmHelp = document.getElementById("confirmHelp");

    // CONFIRM PASSWORD MATCH CHECK
    if (confirmInput && confirmHelp && passwordInput) {
        confirmInput.addEventListener("input", function () {
            const pass = passwordInput.value;
            const confirmPass = this.value;

        if (confirmPass.length === 0) {
            confirmHelp.classList.add("d-none");
            confirmHelp.innerHTML = "";
            return;
        }

            if (pass !== confirmPass) {
                confirmHelp.innerHTML = "Password and Confirm Password does not match.";
                confirmHelp.classList.remove("d-none");
            } else {
                confirmHelp.classList.add("d-none");
                confirmHelp.innerHTML = "";
            }
        });
    }

</script>

{{--password show script--}}
<script>
    function togglePassword() {
        const input = document.getElementById("password");
        const icon = document.getElementById("toggleIcon");

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("bi-eye-slash");
            icon.classList.add("bi-eye");
        } else {
            input.type = "password";
            icon.classList.remove("bi-eye");
            icon.classList.add("bi-eye-slash");
        }
    }
</script>
