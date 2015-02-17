<?
/* @var $templateInfo \Foomo\TypeScript\TemplateRenderer\TemplateInfo */
?>
// this is a generated file - DO NOT EDIT !!!! <? // not this file ;) ?>

module <?= $model->module ?> {
export class Templates {
<? foreach($templates as $templateInfo): ?>
	public static <?= $templateInfo->name ?>() {
	return _.template(<?= json_encode($templateInfo->getTemplateContents()) ?>);
	}
<? endforeach; ?>
}
}