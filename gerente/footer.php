<?php
$verCore        = filemtime(__DIR__ . '/js/core.js');
$verComponentes = filemtime(__DIR__ . '/js/componentes.js');
$verPaginas     = filemtime(__DIR__ . '/js/paginas.js');
$verPerfil      = filemtime(__DIR__ . '/../perfil/perfil.js');
?>
</main>
    </div> 
</div> 

<script>window.I18N = <?= i18nParaJs() ?>;</script>
<script src="js/core.js?v=<?= $verCore ?>"></script>
<script src="js/componentes.js?v=<?= $verComponentes ?>"></script>
<script src="js/paginas.js?v=<?= $verPaginas ?>"></script>
<script src="../perfil/perfil.js?v=<?= $verPerfil ?>"></script>
</body>
</html>
