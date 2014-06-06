<?php
namespace samson\cms\web\material;

use samson\activerecord\dbRelation;

use samson\activerecord\dbConditionArgument;

use samson\activerecord\dbConditionGroup;

use samson\cms\cmsmaterial;
use samson\cms\CMSNav;
use samson\activerecord\dbQuery;
use samson\pager\Pager;
use samson\cms\CMSNavMaterial;

class App extends \samson\cms\App
{
	/** Application name */
	public $name = 'Материалы';
	
	/** Identifier */
	protected $id = 'material';
	
	/** Table rows count */
	protected $table_rows = 15;
	
	/** Dependencies */
	protected $requirements = array('ActiveRecord');
		
	/** Controllers */
	
	/** Generic controller */
	public function __handler( $cmsnav = null, $search = null, $page = null )
	{		
		// Try to find cmsnav
		if(isset($cmsnav)) $cmsnav = cmsnav( $cmsnav ,'id');		
		
		// Old-fashioned direct form post
		if( isset($_POST['search'])) $search = $_POST['search'];
		
		// Set view data
		$this
			->view('index')
			->title( t($this->name, true))
			->cmsnav( $cmsnav )
			->cmsnav_id( isset($cmsnav) ? $cmsnav->id : '0' )
			->search($search)
			->set( $this->__async_table( $cmsnav, $search, $page  ) )
		;
		
		// Render mainmenu submenu
		//m('menu')->submenu( m()->all_materials( '1')->output('sub_menu') );		
	}
	
	/** Generic material form controller */
	public function __form( $material_id = null, $cmsnav = null )
	{			
		// Create form object
		$form = new \samson\cms\web\material\Form( $material_id );
	
		// Render form
		m()->html( $form->render() );
	}

	
	/** Main logic */	
	
	/** Async form */
	function __async_form( $material_id = null, $cmsnav = null )
	{
		// Create form object
		$form = new \samson\cms\web\material\Form( $material_id );
		
		// Success
		return array( 'status' => TRUE, 'form' => $form->render(), 'url' => 'material/form/'.$material_id );
	}
	
	/** Async materials save */
	function __async_save()
	{		
		// If we have POST data
		if( isset($_POST) )
		{
			// Create empty object
			/* @var $db_material \samson\cms\CMSMaterial */			
			$db_material = new \samson\cms\CMSMaterial(false);
			
			// If material identifier is passed and it's valid
			if( isset($_POST['MaterialID']) && $_POST['MaterialID'] > 0 )
			{
				$db_material = dbQuery('samson\cms\cmsmaterial')->id($_POST['MaterialID'])->first();
			}
			// New material creation
			else
			{
				// Fill creation ts
				$db_material->Created = date('h:m:i d.m.y');				
				$db_material->Active = 1;				
			}			
			
			// Make it not draft
			$db_material->Draft = 0;
			
			if( isset( $_POST['Name'] )) 		$db_material->Name = $_POST['Name'];		
			if( isset( $_POST['Published'] )) 	$db_material->Published = $_POST['Published'];
			if( isset( $_POST['Url'] )) 		$db_material->Url = $_POST['Url'];		
			
			// Save object to DB
			$db_material->save();
			
			// Clear existing relations between material and cmsnavs
			foreach ( dbQuery('samson\cms\cmsnavmaterial')->MaterialID( $db_material->id )->exec() as $cnm ) $cnm->delete(); 
			
			// Iterate relations between material and cmsnav
			if( isset( $_POST['StructureID'] )) foreach( $_POST['StructureID'] as $cmsnav_id )
			{		
				// Save record
				$sm = new CMSNavMaterial(false);
				$sm->MaterialID = $db_material->id;
				$sm->StructureID = $cmsnav_id;
				$sm->Active = 1;
				$sm->save();
			}
			
			// Success
			return array_merge( array( 'status' => TRUE ), $this->__async_form($db_material->id) );
		}
		
		// Fail
		return array_merge( array( 'status' => FALSE ) );
	}
	
	/**
	 * Render materials table and pager
	 * @param string $cmsnav 	Parent CMSNav identifier
	 * @param string $search	Keywords to filter table
	 * @param string $page		Current table page	 
	 * @return array Collection of rendered table and pager data
	 */
	function __async_table( $cmsnav = null, $search = null, $page = null  )
	{			
		// Try to find cmsnav
		$cmsnav = cmsnav( $cmsnav, 'id');		
		
		// Generate materials table		
		$table = new Table( $cmsnav, $search, $page );
				
		// Add aditional material fields
		$ocg = new dbConditionGroup('OR');
		foreach ( cms()->material_fields as $f) 
		{
			// Create special condition for additional fields
			$cg = new dbConditionGroup('AND');
			$cg->arguments[] = new dbConditionArgument('_mf.FieldID', $f->FieldID);
			$cg->arguments[] = new dbConditionArgument('_mf.Value', '%'.$search.'%', dbRelation::LIKE );
			
			$ocg->arguments[] = $cg;				
		}
		
		// Add condition group
		$table->search_fields[] = $ocg;
		
		// Render table and pager
		return array_merge( array( 'status' => TRUE ), $table->toView( 'table_' ), $table->pager->toView( 'pager_'));			
	}
	
	/**
	 * Publish/Unpublish material
	 * @param mixed $_cmsmat Pointer to material object or material identifier 
	 * @return array Operation result data
	 */
	function __async_publish( $_cmsmat )
	{
		// Get material safely 
		if( cmsquery()->id($_cmsmat)->first( $cmsmat ) )
		{
			// Toggle material published status
			$cmsmat->Published = $cmsmat->Published ? 0 : 1;
			
			// Save changes to DB
			$cmsmat->save();
			
			// Действие не выполнено
			return array( 'status' => TRUE );
		}		
		// Return error array
		else return array( 'status' => FALSE, 'message' => 'Material "'.$_cmsmat.'" not found');		
	}
	
	/**
	 * Delete material
	 * @param mixed $_cmsmat Pointer to material object or material identifier
	 * @return array Operation result data
	 */
	function __async_remove( $_cmsmat )
	{
		// Get material safely 
		if( cmsquery()->id($_cmsmat)->first( $cmsmat ) )
		{				
			// Mark material as deleted
			$cmsmat->Active = 0;
			
			// Save changes to DB
			$cmsmat->save();	
			
			// Действие не выполнено
			return array( 'status' => TRUE );
		}		
		// Return error array
		else return array( 'status' => FALSE, 'message' => 'Material "'.$_cmsmat.'" not found');
	}
	
// 	/**
// 	 * Copy material
// 	 * @param mixed $_cmsmat Pointer to material object or material identifier
// 	 * @return array Operation result data
// 	 */
// 	function __async_copy( $_cmsmat )
// 	{
// 		return array( 'status' => FALSE, 'message' => 'Material "'.$_cmsmat.'" not found');
		
// 		// Get material safely 
// 		if( cmsquery()->id($_cmsmat)->first( $cmsmat ) )
// 		{
// 			// Toggle material published status
// 			$cmsmat->Published = $cmsmat->Published ? 0 : 1;
			
// 			// Save changes to DB
// 			$cmsmat->save();
			
// 			// Действие не выполнено
// 			return array( 'status' => TRUE );
// 		}		
// 		// Return error array
// 		else return array( 'status' => FALSE, 'message' => 'Material "'.$_cmsmat.'" not found');
// 	}
	
	/** Output for main page */
	public function main()
	{			
		// Получим все материалы
		if( dbQuery('samson\cms\cmsmaterial')->join('user')->Active(1)->Draft(0)->order_by('Created','DESC')->limit(5)->exec($db_materials) )
		{
			// Render material rows
			$rows_html = '';
			foreach ( $db_materials as $db_material ) $rows_html .= $this->view('main/row')
			->material($db_material)
			->user($db_material->onetoone['_user'])			
			->output();		

			for ($i = sizeof($db_materials); $i < 5; $i++) 
			{
				$rows_html .= $this->view('main/row')->output();	
			}
			
			// Render main template
			return $this->rows( $rows_html )->output('main/index');
		}		
	}
}