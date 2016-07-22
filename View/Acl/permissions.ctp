<div class="form">
<h3><?php echo sprintf(__("%s permissions"), $aroAlias); ?></h3>
<p><?php echo $this->Paginator->counter(['format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%')]); ?></p>
<div class="paging">
	<?php echo $this->Paginator->prev('<< ' . __('previous'), [], null, ['class'=>'disabled']);?>
	| <?php echo $this->Paginator->numbers();?> |
	<?php echo $this->Paginator->next(__('next') . ' >>', [], null, ['class' => 'disabled']);?>
</div>
<?php echo $this->Form->create('Perms'); ?>
<table>
	<tr>
		<th>Action</th>
		<?php foreach ($aros as $aro) : ?>
		<?php $aro = array_shift($aro); ?>
		<th><?php echo h($aro[$aroDisplayField]); ?></th>
		<?php endforeach; ?>
	</tr>
<?php
$uglyIdent = Configure::read('AclManager.uglyIdent');
$lastIdent = null;
foreach ($acos as $id => $aco) {
	$action = $aco['Action'];
	$alias = $aco['Aco']['alias'];
	$ident = substr_count($action, '/');
	if ($ident <= $lastIdent && !is_null($lastIdent)) {
		for ($i = 0; $i <= ($lastIdent - $ident); $i++) {
			?></tr><?php
		}
	}
	if ($ident != $lastIdent) {
		?><tr class='aclmanager-ident-<?php echo $ident; ?>'><?php
	}
	?><td><?php echo ($ident == 1 ? "<strong>" : "" ) . ($uglyIdent ? str_repeat("&nbsp;&nbsp;", $ident) : "") . h($alias) . ($ident == 1 ? "</strong>" : "" ); ?></td>
	<?php foreach ($aros as $aro) :
		$inherit = $this->Form->value("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}-inherit");
		$allowed = $this->Form->value("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}");
		$value = $inherit ? 'inherit' : null;
		$icon = $this->Html->image(($allowed ? 'test-pass-icon.png' : 'test-fail-icon.png')); ?>
		<td><?php echo $icon . " " . $this->Form->select("Perms." . str_replace("/", ":", $action) . ".{$aroAlias}:{$aro[$aroAlias]['id']}", [['inherit' => __('Inherit'), 'allow' => __('Allow'), 'deny' => __('Deny')]], ['empty' => __('No change'), 'value' => $value]); ?></td>
	<?php endforeach; ?>
<?php
	$lastIdent = $ident;
}
for ($i = 0; $i <= $lastIdent; $i++) {
	?></tr><?php
}
?></table>
<?php
echo $this->Form->end(__("Save"));
?>
<p><?php echo $this->Paginator->counter(['format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%')]); ?></p>
<div class="paging">
	<?php echo $this->Paginator->prev('<< ' . __('previous'), [], null, ['class'=>'disabled']);?>
	| <?php echo $this->Paginator->numbers();?> |
	<?php echo $this->Paginator->next(__('next') . ' >>', [], null, ['class' => 'disabled']);?>
</div>
</div>
<div class="actions">
	<h3><?php echo __('Manage for'); ?></h3>
	<?php
	$aroModels = Configure::read("AclManager.aros");
	if ($aroModels > 1) : ?>
		<ul><?php foreach ($aroModels as $aroModel) : ?>
				<li><?php echo $this->Html->link($aroModel, ['aro' => $aroModel]); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?= $this->Html->link(__('< Back'), ['action' => 'index']); ?></li>
		<li><?= $this->Html->link(__('Manage permissions'), ['action' => 'permissions']); ?></li>
		<li><?= $this->Html->link(__('Update ACOs'), ['action' => 'update_acos']); ?></li>
		<li><?= $this->Html->link(__('Update AROs'), ['action' => 'update_aros']); ?></li>
		<li><?= $this->Html->link(__('Drop ACOs/AROs'), ['action' => 'drop'], [], __("Do you want to drop all ACOs and AROs?")); ?></li>
		<li><?= $this->Html->link(__('Drop permissions'), ['action' => 'drop_perms'], [], __("Do you want to drop all the permissions?")); ?></li>
	</ul>
</div>
