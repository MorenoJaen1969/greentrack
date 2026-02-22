<?php
// app/views/content/parametros-login-view.php
?>

<div class="container-fluid d-flex justify-content-center align-items-center" style="min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="card shadow-lg" style="width: 400px;">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0">
                <i class="fas fa-cog mr-2"></i>General Parameters Access
            </h4>
        </div>
        <div class="card-body">
            <form id="parametrosLoginForm">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user mr-2"></i>Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-key mr-2"></i>Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div id="loginMessage" class="alert d-none" role="alert"></div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt mr-2"></i>Access Parameters
                </button>
            </form>
        </div>
        <div class="card-footer text-center">
            <a href="/dashboard" class="text-muted">
                <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('parametrosLoginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Authenticating...';
    
    const formData = {
        modulo_parametros: 'autenticar',
        username: document.getElementById('username').value,
        password: document.getElementById('password').value
    };
    
    fetch('/app/ajax/datosgeneralesAjax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('loginMessage');
        
        if (data.success) {
            messageDiv.className = 'alert alert-success d-block';
            messageDiv.textContent = 'Access granted. Redirecting...';
            
            setTimeout(() => {
                window.location.href = '/parametros';
            }, 1500);
        } else {
            messageDiv.className = 'alert alert-danger d-block';
            messageDiv.textContent = data.message || 'Authentication failed';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Access Parameters';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const messageDiv = document.getElementById('loginMessage');
        messageDiv.className = 'alert alert-danger d-block';
        messageDiv.textContent = 'Connection error. Please try again.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Access Parameters';
    });
});
</script>