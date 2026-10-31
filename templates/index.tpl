{extends file="layouts/backend.tpl"}

{block name="breadcrumbs"}{/block}

{block name="page"}
	<h1 class="app__pageHeading" style="margin-bottom: 20px;">{$pageTitle|escape}</h1>
	{$formHtml}
{/block}
