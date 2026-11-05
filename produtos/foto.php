<?php
require_once('processa/foto_processa.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit();
}

include(HEADER_TEMPLATE);
?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<?php if (!empty($sucesso)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>

<?php if ($produto): ?>
    <h2><i class="fas fa-camera"></i> Foto do produto</h2>

    <div class="alert alert-info">
        <strong>ID:</strong> <?php echo htmlspecialchars($produto['ID']); ?><br>
        <strong>Produto:</strong> <?php echo htmlspecialchars($produto['Descricao']); ?>
    </div>

    <form action="foto.php?id=<?php echo intval($produto['ID']); ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo intval($produto['ID']); ?>">

        <div class="row">
            <div class="col-md-5">
                <label class="fw-bold">Imagem atual</label>
                <div class="produto-foto-preview" id="produto-foto-preview">
                    <?php if (!empty($foto_atual_src)): ?>
                        <img src="<?php echo htmlspecialchars($foto_atual_src); ?>" alt="Foto atual do produto">
                    <?php else: ?>
                        <div class="produto-foto-placeholder">
                            <i class="fas fa-box-open"></i>
                            <span>Sem foto</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-7">
                <div class="form-group mb-3">
                    <label class="fw-bold" for="foto">Adicionar ou trocar foto</label>
                    <input type="file" class="form-control" name="foto" id="foto" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small class="text-muted">Formatos aceitos: JPG, PNG, WEBP ou GIF. Tamanho maximo: 2 MB.</small>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="index.php" class="btn btn-outline-success">Voltar</a>
                    <button type="submit" name="acao" value="salvar" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar foto
                    </button>
                    <?php if (!empty($foto_atual_src)): ?>
                        <button type="submit" name="acao" value="remover" class="btn btn-outline-danger"
                            onclick="return confirm('Remover a foto deste produto?');">
                            <i class="fas fa-trash-alt"></i> Remover foto
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<style>
    .produto-foto-preview {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 280px;
        padding: 12px;
        background: #fff;
        border: 1px solid #d7dee5;
        border-radius: 8px;
    }

    .produto-foto-preview img {
        display: block;
        width: 100%;
        max-height: 320px;
        object-fit: contain;
        border-radius: 6px;
    }

    .produto-foto-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        min-height: 250px;
        color: #2f6848;
        background: #eef4f1;
        border-radius: 8px;
    }

    .produto-foto-placeholder i {
        font-size: 2.4rem;
    }

    .produto-foto-placeholder span {
        font-weight: 700;
    }
</style>

<script>
    $(document).ready(function() {
        $('#foto').on('change', function() {
            const arquivo = this.files && this.files[0] ? this.files[0] : null;

            if (!arquivo) {
                return;
            }

            const leitor = new FileReader();
            leitor.onload = function(evento) {
                $('#produto-foto-preview').html('<img src="' + evento.target.result + '" alt="Previa da foto do produto">');
            };
            leitor.readAsDataURL(arquivo);
        });
    });
</script>

<?php include(FOOTER_TEMPLATE); ?>
