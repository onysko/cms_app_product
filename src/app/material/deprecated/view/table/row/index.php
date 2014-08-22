<tr <?php isnval('cmsmaterial_Draft',0,'class="draft"')?>>
	<td class="id"><span class="cell-inner"><?php v('cmsmaterial_id');?></span></td>				
	<td class="name limited">		
		<a href="<?php module_url('form','cmsmaterial_id')?>" title="Редактировать материал: <?php v('cmsmaterial_Name')?>"><?php v('cmsmaterial_Name')?></a>
		<?php if(isv('draft_id')):?>
			<a class="draft-link" href="<?php module_url('form','draft_id')?>" title="Черновик - <?php vdate('draft_Modyfied','H:i:s d.m.Y')?>"></a>
		<?php endif?>	
	</td>
	<td class="url limited">
		<a class="inner" href="<?php v('cmsmaterial_Url')?>" title="Открыть материал на сайте: <?php v('cmsmaterial_Url')?>">
			<?php v('cmsmaterial_Url')?>
		</a>
	</td>
	<td class="structure">
		<?php if(isv('navs')) foreach ($navs as $structure ):?>			
			<a class="inner" title="Перейти к материалам ЭСС" href="<?php module_url($structure->id)?>">
				<?php $structure->Name()?>
			</a>
		<?php endforeach;?>
	</td>
	<td class="author">		
		<?php v('user_SName');?><br><?php v('user_FName');?>		
	</td>
	<td class="modyfied">		
		<?php v('cmsmaterial_Created'); ?><br>
		<?php isval('cmsmaterial_Draft',0,'cmsmaterial_Modyfied')?>			
	</td>
	<td class="published" title="Опубликовать/Скрыть материал">		
		<a class="publish_href" href="<?php module_url( 'publish/table', 'cmsmaterial_id', 'nav_id', 'search', 'pager_current_page' ) ?>"></a>
		<input type="checkbox" value="<?php v('cmsmaterial_id'); ?>" name="published" id="published" <?php isval('cmsmaterial_Published','1','checked')?>>	
	</td>
	<td class="control">					
		<a class="icon2 icon_16x16 icon-edit" href="<?php module_url('form','cmsmaterial_id')?>" title="Редактировать текущий материал"></a>	
		<a class="icon2 icon_16x16 icon-copy-material copy" href="<?php module_url('copy/table','cmsmaterial_id', 'nav_id', 'search','pager_current_page')?>" title="Создать копию материала"></a>
		<a class="icon2 icon_16x16 icon-delete delete" href="<?php module_url('remove/table', 'cmsmaterial_id', 'nav_id', 'search','pager_current_page')?>" title="Удалить текущий материал"></a>
	</td>			
</tr>