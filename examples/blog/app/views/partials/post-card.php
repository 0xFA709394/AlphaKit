<?php
/** 子视图(被 insert 调用;传入 Post 模型或数组均可) */
/** @var mixed $card */
/** @var \AlphaKit\Core\View\ViewEngine $this */
$isModel = is_object($card);
$id = $isModel ? $card->id : ($card['id'] ?? '');
$title = $isModel ? $card->title : ($card['title'] ?? '');
$views = $isModel ? $card->views : ($card['views'] ?? 0);
$content = $isModel ? $card->content : ($card['content'] ?? '');
$createdAt = $isModel ? $card->created_at : ($card['created_at'] ?? '');
?>
<div class="card">
    <a href="<?= \url('post.show', ['id' => $id]) ?>" style="color:inherit;text-decoration:none">
        <h1 style="font-size:18px"><?= \e((string)$title) ?></h1>
    </a>
    <div class="muted">👁 <?= (int)$views ?> 次浏览 · <?= \e((string)$createdAt) ?></div>
    <p style="margin-top:6px"><?= \e(mb_substr((string)$content, 0, 80)) ?>…</p>
</div>
