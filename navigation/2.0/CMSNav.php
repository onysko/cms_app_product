<?php
/**
 * Created by PhpStorm.
 * User: p.onysko
 * Date: 03.03.14
 * Time: 13:46
 */
namespace samson\cms\web;


use samson\activerecord\dbRelation;

class CMSNav extends \samson\cms\CMSNav
{
    public $currentNavID = 0;
    /**
     * Help method for sorting structures
     * @param $str1 \samson\cms\CMSNav
     * @param $str2 \samson\cms\CMSNav
     *
     * @return bool
     */
    public static function strSorting($str1, $str2)
    {
        return $str1['PriorityNumber']>$str2['PriorityNumber'];
    }

    /**
     * Create select tag with selected parent
     * @param int $parentID selected structure identifier
     *
     * @return string html view for select
     */
    public static function createSelect($parentID = 0)
    {
        $select = '';
        $data = null;
        $mewdata = null;
        if (dbQuery('\samson\cms\web\CMSNav')->StructureID($parentID)->first($data)) {
            $select .= '<option title="'.$data->Name.'" selected value="'.$data->id.'">'.$data->Name.'</option>';
        } else {
            $select .= '<option title="Не выбрано" value="Не выбрано">Не выбрано</option>';
        }
        if (dbQuery('\samson\cms\web\CMSNav')->exec($newdata)) {
            foreach ($newdata as $key=>$val) {
                $select .= '<option title="'.$val->Name.'" value="'.$val->id.'">'.$val->Name.'</option>';
            }
        }
        return $select;
    }
    /**
     * Filling the fields and creating relation of structure
     */
    public function fillFields()
    {
        // Fill the fields from $_POST array
        foreach ($_POST as $key => $val) {
            $this[$key]=$val;
        }

        // Save object
        $this->save();

        if (isset($_POST['ParentID']) && $_POST['ParentID'] != 0) {
            // Create new relation
            $strRelation = new \samson\activerecord\structure_relation(false);
            $strRelation->parent_id = $_POST['ParentID'];
            $strRelation->child_id = $this->id;

            // Save relation
            $strRelation->save();
        }
    }

    /**
     * Updating structure
     */
    public function update()
    {
        /** @var array $relations array of structure_relation objects */
        $relations = null;

        // If CMSNav has old relations then delete it
        if (dbQuery('\samson\activerecord\structure_relation')->child_id($this->id)->exec($relations)) {
            /** @var \samson\activerecord\structure_relation $relation */
            foreach ($relations as $relation) {
                $relation->delete();
            }
        }

        // Update new fields
        $this->fillFields();
    }

    public static function fullTree(CMSNav & $parent = null)
    {
        $html = '';

        if (!isset($parent)) {
            $parent = new CMSNav(false);
            $parent->Name = 'Корень навигации';
            $parent->Url = 'NAVIGATION_BASE';
            $parent->StructureID = 0;
            $parent->base = 1;
            $db_navs = null;
            if (dbQuery('samson\cms\web\cmsnav')
                ->Active(1)
                ->join('parents_relations')
                ->cond('parents_relations.parent_id', '', dbRelation::ISNULL)
                ->exec($db_navs)) {
                foreach ($db_navs as $db_nav) {
                    $parent->children['id_'.$db_nav->id] = $db_nav;
                }
            }
        }

        $htmlTree = $parent->htmlTree($parent, $html, 'tree.element.tmpl.php', 0, $parent->currentNavID);

        return $htmlTree;
    }

    public function htmlTree(CMSNav & $parent = null, & $html = '', $view = null, $level = 0, $currentNavID = 0)
    {
        if (!isset($parent)) {
            $parent = & $this;
        }
        if ($parent->base) {
            $children = $parent->children();
        } else {
            $children = $parent->baseChildren();
        }
        usort($children, array('\samson\cms\web\CMSNav', 'strSorting'));

        if (sizeof($children)) {
            $html .= '<ul>';
            foreach ($children as $child) {
                if (isset($view)) {
                    $html .= '<li>'.m()->view($view)->parentid($parent->id)->nav_id($currentNavID)->db_structure($child)
                            ->output().'';
                } else {
                    $html .= '<li>'.$child->Name.'</li>';
                }
                //if ($level < 5)
                    $parent->htmlTree($child, $html, $view, $level++, $currentNavID);
                $html .= '</li>';
            }
            $html .='</ul>';
        }

        return $html;
    }
}
