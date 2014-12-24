<?php
namespace samson\cms\web\product;

use samson\activerecord\dbRelation;
use samson\activerecord\dbConditionArgument;
use samson\activerecord\dbConditionGroup;
use samson\cms\cmsmaterial;
use samson\cms\CMSNav;
use samson\activerecord\dbQuery;
use samson\pager\Pager;
use samson\cms\CMSNavMaterial;

/**
 * SamsonCMS generic material application.
 *
 * This application covers all actions that can be done
 * with materials and related entities in SamsonCMS.
 *
 * @package samson\cms\web\material
 */
class ProductApplication extends \samson\cms\App
{
	/** Application name */
	public $app_name = 'Товары';
	
	/** Identifier */
	protected $id = 'product';
	
	/** Table rows count */
	protected $table_rows = 15;

    protected $catalogID = 4;

    protected $brandField = 'company_id';
		
	/** Controllers */
	
	/** Generic controller */
	public function __handler($cmsnav = null, $company = 0, $search = null, $page = null)
	{
        // Generate localized title
        $title = t($this->app_name, true);

        // Set view scope
        $this->view('index');

		// Try to find cmsnav
		if (isset($cmsnav) && dbQuery('\samson\cms\Navigation')->id($cmsnav)->first($cmsnav)) {
            // Add structure title
            $title = t($cmsnav->Name, true).' - '.$title;
        } else {
            $cmsnav = dbQuery('\samson\cms\Navigation')->id($this->catalogID)->first();
        }

        // Pass Navigation to view
        $this->cmsnav($cmsnav);

		// Old-fashioned direct search input form POST if not passed
        $search = !isset($search) ? (isset($_POST['search']) ? $_POST['search'] : '') : $search;

        m()->company_id($company);


		// Set view data
		$this
            ->title($title)
            ->cmsnav_id($this->catalogID)
			->set($this->__async_table($cmsnav, $company, $search, $page))
		;
	}

    /** Generic material form controller */
    public function __form($material_id = null, $cmsnav = null)
    {
        // Create form object
        $form = new \samson\cms\web\material\Form( $material_id, $cmsnav );

        if ($material_id == 0) {
            m()->new_material(true);
        }
        // Render form
        m()->html( $form->render() );
    }

    /** Main logic */

    /** Async form */
    function __async_form($material_id = null)
    {
        // Create form object
        $form = new \samson\cms\web\material\Form( $material_id );

        // Success
        return array( 'status' => TRUE, 'form' => $form->render(), 'url' => 'product/form/'.$material_id );
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
            if( isset( $_POST['type'] )) 	$db_material->type = $_POST['type'];
            if( isset( $_POST['Url'] )) 		$db_material->Url = $_POST['Url'];

            // Save object to DB
            $db_material->save();

            // Clear existing relations between material and cmsnavs
            foreach ( dbQuery('samson\cms\CMSNavMaterial')->MaterialID( $db_material->id )->exec() as $cnm ) $cnm->delete();

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
	function __async_table($cmsnav = null, $company = null, $search = null, $page = null)
	{
		// Try to find cmsnav
        if (isset($cmsnav) && (is_object($cmsnav) || dbQuery('\samson\cms\Navigation')->id($cmsnav)->first($cmsnav))) {
            if ($cmsnav->id != $this->catalogID) {
                $parent = $cmsnav->parent();
            }
            // Handle successfull found
        } else {
            $cmsnav = dbQuery('\samson\cms\Navigation')->id($this->catalogID)->first();
            $parent = $cmsnav;
        }

		// Generate materials table		
		$table = new Table($cmsnav, $company, $search, $page);

        $table_html = $table->render();

        $pager_html = $table->pager->toHTML();

        if (!isset($parent)) {
            $parent = $cmsnav;
        }
        //$catalog = dbQuery('\samson\cms\Navigation')->id($this->catalogID)->first();

        $tree = new \samson\treeview\SamsonTree('tree/tree-template', 0, 'product/addchildren');

		// Render table and pager
		return array('status' => 1, 'table_html' => $table_html, 'pager_html' => $pager_html, 'tree' => $tree->htmlTree($parent));
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

    public function __async_move($structureID)
    {
        /** @var \samson\cms\web\navigation\CMSNav $cmsnav */
        $cmsnav = null;

        if (isset($_POST['materialIds']) && !empty($_POST['materialIds']) && dbQuery('\samson\cms\Navigation')->id($structureID)->first($cmsnav)) {
            if (dbQuery('samson\cms\CMSNavMaterial')->cond('MaterialID', $_POST['materialIds'])->cond('StructureID', 4123, dbRelation::NOT_EQUAL)->exec($data)) {
                $currentNav = $cmsnav;
                foreach ($data as $strmat) {
                    $strmat->delete();
                }

                foreach ($_POST['materialIds'] as $matID) {
                    $cmsnav = $currentNav;
                    $material = dbQuery('material')->id($matID)->first();
                    $material->category = $cmsnav->Url;
                    $material->save();
                    while (isset($cmsnav)) {
                        $strmat = new \samson\activerecord\structurematerial(false);
                        $strmat->MaterialID = $matID;
                        $strmat->StructureID = $cmsnav->id;
                        $strmat->Active = 1;
                        $strmat->save();
                        if ($cmsnav->Url == 'katalog') {
                            break;
                        } else {
                            $cmsnav = $cmsnav->parent();
                        }
                    }
                }
            }
        }

        return $this->__async_table($structureID);
    }

    public function __async_structuredelete($structureID)
    {
        /** @var \samson\cms\Navigation $cmsnav */
        $cmsnav = null;
        if (dbQuery('\samson\cms\Navigation')->id($structureID)->first($cmsnav)) {
            foreach ($cmsnav->materials() as $material) {
                $material->Active = 0;
                $material->save();
            }

            $parent = $cmsnav->parent();

            $cmsnav->Active = 0;
            $cmsnav->save();
        }

        $tree = new \samson\treeview\SamsonTree('tree/tree-template', 0, 'product/addchildren');

        //$catalog = dbQuery('\samson\cms\Navigation')->id($this->catalogID)->first();

        return array('status' => 1, 'tree' => $tree->htmlTree($parent));
    }

    public function __async_structureupdate($structureID = null)
    {
        /** @var \samson\cms\web\navigation\CMSNav $data */
        $data = null;

        $strIds = dbQuery('structure_relation')->cond('child_id', $structureID)->fields('parent_id');
        if (dbQuery('\samson\cms\web\navigation\CMSNav')->StructureID($structureID)->first($data)) {
            // Update structure data
            $data->update();

            $cmsnav = $data;

            foreach ($data->materials() as $material) {
                $data = $cmsnav;
                foreach (dbQuery('structurematerial')->cond('MaterialID', $material->id)->cond('StructureID', $strIds)->exec() as $relation) {
                    $relation->delete();
                }
                while ($data) {
                    $strMat = new \samson\activerecord\structurematerial(false);
                    $strMat->Active = 1;
                    $strMat->StructureID = $data->id;
                    $strMat->MaterialID = $material->id;
                    $strMat->save();

                    if ($data->id == $this->catalogID) {
                        break;
                    } else {
                        $data = $data->parent();
                    }
                }
            }
        } else {
            // Create new structure
            $nav = new \samson\cms\web\navigation\CMSNav(false);
            $nav->fillFields();
        }

        if (isset($structureID)) {
            $parent_id = $structureID;
        } else {
            $parent_id = $_POST['ParentID'];
        }

        return $this->__async_table(dbQuery('\samson\cms\Navigation')->id($parent_id)->first());
    }

    public function __async_movestructure($childID, $parentID)
    {
        $child = dbQuery('\samson\cms\Navigation')->id($childID)->first();
        $child->ParentID = $parentID;
        $child->save();
        $strIds = array();
        $cmsnav = $child->parent();
        while ($cmsnav) {
            $strIds[] = $cmsnav->id;
            if ($cmsnav->id == $this->catalogID) {
                break;
            }
            $cmsnav = $cmsnav->parent();
        }

        if (dbQuery('structure_relation')->cond('child_id', $childID)->exec($strRelations)) {
            foreach ($strRelations as $strRelation) {
                $strRelation->delete();
            }
        }

        // Create new relation with new parent
        $strRelation = new \samson\activerecord\structure_relation(false);
        $strRelation->child_id = $childID;
        $strRelation->parent_id = $parentID;
        $strRelation->save();


        // Create array of structure ids which we need to use to create structurematerial relations
        $relIds = array($parentID);
        // Get relations of new parent
        $stRel = dbQuery('structure_relation')->child_id($parentID)->exec();
        while ($stRel) {
            // Save ids for loop query
            $ids = array();
            // Break flag
            $break = false;
            foreach ($stRel as $strR) {
                // Save current relation id
                $ids[] = $strR->id;

                // Save parent
                $relIds[] = $strR->parent_id;
                if ($strR->parent_id == $this->catalogID) {
                    $break = true;
                    break;
                }
            }
            if ($break) {
                break;
            } else {
                // Get next relations
                $stRel = dbQuery('structure_relation')->child_id($relIds)->exec();
            }
        }

        // Get materials of current category
        if (\samson\cms\CMS::getMaterialsByStructures($childID, $materials)) {
            // Create new structurematerial relations
            foreach ($materials as $material) {

                // Delete old structurematerial relations
                foreach (dbQuery('structurematerial')->cond('MaterialID', $material->id)->cond('StructureID', $strIds)->exec() as $relation) {
                    $relation->delete();
                }

                // Create new relations
                foreach ($relIds as $relId) {
                    $strMat = new \samson\activerecord\structurematerial(false);
                    $strMat->Active = 1;
                    $strMat->StructureID = $relId;
                    $strMat->MaterialID = $material->id;
                    $strMat->save();
                }
            }
        }

        return array('status' => 1);
    }

    public function __async_addchildren($structure_id)
    {
        if (dbQuery('\samson\cms\Navigation')->StructureID($structure_id)->first($db_structure)) {
            $tree = new \samson\treeview\SamsonTree('tree/tree-template', 0, 'product/addchildren');
            return array('status' => 1, 'tree' => $tree->htmlTree($db_structure));
        }

        return array('status' => 0);
    }
}