{capture name=path}<a href="{$base_dir_ssl}history.php">{l s='Order history' mod='fondy'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Fondy payment' mod='Fondy'}{/capture}
{include file=$tpl_dir./breadcrumb.tpl}

<h2>Fondy payment</h2>

<div class="success">{$message}</div>

<br><br>

<a href="{$base_dir_ssl}history.php">{l s='Review your order' mod='fondy'}</a>
