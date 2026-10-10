<div class="wrap sec">
  <div class="tile" style="background:var(--accent2);padding:clamp(24px,5vw,56px);gap:18px">
    <span class="bdg" style="align-self:start"><?= (int)$code ?></span>
    <h1 class="kin" style="font-size:clamp(40px,7vw,104px)"><?= e($title) ?></h1>
    <?php if ($msg): ?><p style="margin:0;max-width:60ch"><?= e($msg) ?></p><?php endif; ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn big" href="/">Αρχική</a><a class="btn big light" href="/products/">Όλα τα προϊόντα</a></div>
  </div>
  <form class="nl" role="search" action="/search/" method="get" style="background:var(--secondary);grid-template-columns:1fr;margin-top:12px">
    <div class="r"><label for="eq" class="sr">Αναζήτηση</label><input id="eq" type="search" name="q" placeholder="Αναζητήστε προϊόν" minlength="2" required style="flex:1;min-width:0;border:0;background:none;padding:0 14px;font:inherit;color:#17195A"><button class="btn">Αναζήτηση</button></div>
  </form>
</div>
