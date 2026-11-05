</div> <!-- /container -->







<!--Bootstrap min-->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.min.js"
    integrity="sha384-ODmDIVzN+pFdexxHEHFBQH3/9/vQ9uori45z4JjnFsRydbmQbmL5t1tQ0culUzyK"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa"
    crossorigin="anonymous"></script>


<!--Select2-->

<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js"></script>





<script src="<?php echo BASEURL; ?>js/main.js"></script>



<!-- Footer -->
<footer class="footer-custom bg-custom text-white shadow-lg border-top">

    <div class="container-fluid">

        <div class="row align-items-center">

            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">

                <span class="fw-semibold">
                    <i class="fas fa-school me-2"></i>
                    Prefeitura Municipal de Angatuba
                </span>

            </div>

            <div class="col-md-6 text-center text-md-end">

                <?php if (isset($_SESSION['usuarioNome'])): ?>
                    <span class="me-3">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo $_SESSION['usuarioNome']; ?>
                    </span>
                <?php endif; ?>

                <span class="opacity-75">
                    &copy; <?php echo date("Y"); ?>
                </span>

            </div>

        </div>

    </div>

</footer>
<!-- Fim do Footer -->






<script>
    $(function() {
        timeout = setTimeout(function() {
            window.location.href = "<?php echo BASEURL; ?>sair.php";
        }, 900000);
    });

    $(document).on('mousemove', function() {
        if (timeout !== null) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(function() {
            window.location.href = "<?php echo BASEURL; ?>sair.php";
        }, 900000);
    });
</script>


</footer>


<!-- Toast Container -->
<div class="toast-container position-fixed end-0 p-3" style="bottom: 60px; z-index: 9999;">
</div>

<script>
    let lastCheckTime = localStorage.getItem('supriadm_last_check_' + (typeof usuarioLogado !== 'undefined' ? usuarioLogado : '0')) || '';
    
    function fetchNotificacoes() {
        const url = '<?php echo BASEURL; ?>api/check_notificacoes.php' + (lastCheckTime ? '?last_check=' + encodeURIComponent(lastCheckTime) : '');
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.server_time) {
                    lastCheckTime = data.server_time;
                    localStorage.setItem('supriadm_last_check_' + (typeof usuarioLogado !== 'undefined' ? usuarioLogado : '0'), lastCheckTime);
                }
                
                if (data.novos_finalizados && data.novos_finalizados.length > 0) {
                    data.novos_finalizados.forEach(id => {
                        showToast('✅ Pedido Finalizado!', `Seu pedido #${id} acabou de ser finalizado.`, 'success');
                    });
                }
                
                if (data.novos_analise && data.novos_analise.length > 0) {
                    data.novos_analise.forEach(id => {
                        showToast('🔎 Novo Pedido para Análise', `O pedido #${id} foi encaminhado e aguarda sua aprovação.`, 'primary');
                    });
                }
            })
            .catch(error => console.error('Erro ao checar notificações:', error));
    }
    
    function showToast(title, message, type) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now() + Math.floor(Math.random() * 1000);
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-bell';
        const color = type === 'success' ? 'text-success' : 'text-primary';
        
        const html = `
            <div id="${toastId}" class="toast shadow-lg border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-light">
                    <i class="fas ${icon} ${color} me-2"></i>
                    <strong class="me-auto ${color}">${title}</strong>
                    <small class="text-muted">agora</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body bg-white rounded-bottom">
                    ${message}
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', html);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 10000 }); // stays for 10 seconds
        toast.show();
        
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    if (typeof usuarioLogado !== 'undefined') {
        fetchNotificacoes();
        setInterval(fetchNotificacoes, 10000); // 10 seconds for real-time feel
    }
</script>


</body>

</html>
<?php
ob_end_flush();
?>