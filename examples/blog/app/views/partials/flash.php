<?php
/** @var \AlphaKit\Core\View\ViewEngine $this */
$flashMsg = app(\AlphaKit\Core\Session::class)->getFlash('message', '');
?>
<?php if ($flashMsg !== ''): ?>
    <div class="flash"><?= \e($flashMsg) ?></div>
<?php endif; ?>
