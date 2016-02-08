{capture name=path}<a href="{$base_dir_ssl}history.php">{l s='Order history' mod='oplata'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Oplata payment' mod='Oplata'}{/capture}
{include file=$tpl_dir./breadcrumb.tpl}

<h2>Oplata payment</h2>

<div class="success">{$message}</div>

<br><br>

<a href="{$base_dir_ssl}history.php">{l s='Review your order' mod='oplata'}</a>
