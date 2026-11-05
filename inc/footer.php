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


</body>

</html>
<?php
ob_end_flush();
?>