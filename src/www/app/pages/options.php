<?php

namespace App\Pages;

use \Zippy\Binding\PropertyBinding as Bind;
use \Zippy\Html\DataList\DataView;
use \Zippy\Html\Form\CheckBox;
use \Zippy\Html\Form\DropDownChoice;
use \Zippy\Html\Form\Form;
use \Zippy\Html\Form\TextArea;
use \Zippy\Html\Form\TextInput;
use \Zippy\Html\Label;
use \Zippy\Html\Link\ClickLink;
use \Zippy\Html\Panel;
use App\System;
use App\Application as App;

class Options extends \App\Pages\Base
{

    private $metadatads;

    public function __construct() {
        parent::__construct();
        if (System::getUser()->userlogin !== 'admin') {
            App::Redirect('\App\Pages\Error', 'Вы не админ');
        }


        $this->add(new Form('common'))->onSubmit($this, 'saveCommonOnClick');
        $this->common->add(new TextInput('firmname'));
        $this->common->add(new DropDownChoice('defstore', \App\Entity\Store::getList())) ;

        $this->common->add(new CheckBox('useval'))->onChange($this, "onVal");
        $this->common->add(new TextInput('cdoll'));
        $this->common->add(new TextInput('ceuro'));
        $this->common->add(new TextInput('crub'));
        //  $this->common->add(new Date('closeddate'));

        $this->add(new Form('shop'))->onSubmit($this, 'saveShopOnClick');
        $this->shop->add(new DropDownChoice('defshowstore', \App\Entity\Store::getList())) ;
        $this->shop->add(new DropDownChoice('defсuststore', \App\Entity\Customer::getList())) ;



        $common = System::getOptions("common");
        if (!is_array($common))
            $common = array();

        $this->common->firmname->setText($common['firmname']);
        $this->common->defstore->setValue($common['defstore']);
        $this->common->cdoll->setText($common['cdoll']);
        $this->common->ceuro->setText($common['ceuro']);
        $this->common->crub->setText($common['crub']);
        $this->common->useval->setChecked($common['useval']);
        // $this->common->closeddate->setDate($common['closeddate']);


        $this->onVal($this->common->useval);


        $shop = System::getOptions("shop");
        if (!is_array($shop))
            $shop = array();
        $this->shop->defshowstore->setValue($shop['defshowstore']);
        $this->shop->defсuststore->setValue($shop['defсuststore']);


        $this->metadatads = new \ZCL\DB\EntityDataSource("\\App\\Entity\\MetaData", "", "description");

        $this->add(new Panel('listpan'));
        $this->listpan->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');
        $this->listpan->filter->add(new CheckBox('fdoc'))->setChecked(true);
        $this->listpan->filter->add(new CheckBox('fdic'))->setChecked(true);
        $this->listpan->filter->add(new CheckBox('frep'))->setChecked(true);
        $this->listpan->filter->add(new CheckBox('freg'))->setChecked(true);
        $this->listpan->filter->add(new CheckBox('fshop'))->setChecked(true);
        $this->listpan->add(new ClickLink('addnew'))->onClick($this, 'addnewOnClick');
        $this->listpan->add(new DataView('metarow', $this->metadatads, $this, 'metarowOnRow'))->Reload();

        $this->add(new Panel('editpan'))->setVisible(false);
        $this->editpan->add(new Form('editform'))->onSubmit($this, 'editformOnSubmit');
        $this->editpan->editform->add(new TextInput('meta_id'));
        $this->editpan->editform->add(new TextInput('edit_description'));
        $this->editpan->editform->add(new TextInput('edit_meta_name'));
        $this->editpan->editform->add(new TextInput('edit_menugroup'));
        $this->editpan->editform->add(new TextArea('edit_notes'));
        $this->editpan->editform->add(new CheckBox('edit_disabled'));

        $this->editpan->editform->add(new DropDownChoice('edit_meta_type', \App\Entity\MetaData::getNames()));
        $this->editpan->add(new ClickLink('mcancel'))->onClick($this, 'mcancelOnClick');
    }

    public function onVal($sender) {
        if ($sender->isChecked()) {
            $this->common->cdoll->setVisible(true);
            $this->common->ceuro->setVisible(true);
            $this->common->crub->setVisible(true);
        } else {
            $this->common->cdoll->setVisible(false);
            $this->common->ceuro->setVisible(false);
            $this->common->crub->setVisible(false);
        }
    }

    public function saveCommonOnClick($sender) {
        $common = array();
        $common['firmname'] = $this->common->firmname->getText();
        $common['defstore'] = $this->common->defstore->getValue();
        $common['cdoll'] = $this->common->cdoll->getText();
        $common['ceuro'] = $this->common->ceuro->getText();
        $common['crub'] = $this->common->crub->getText();
        $common['useval'] = $this->common->useval->isChecked();
        // $common['closeddate'] = $this->common->closeddate->getDate();

        System::setOptions("common", $common);

        System::setOptions("shop", $shop);
        $this->setSuccess('Сохранено');
    }

    public function saveShopOnClick($sender) {
        $shop = array();

        //todo контрагент магазина, кому  нотификацию

        $shop['defshowstore'] = $this->shop->defshowstore->getValue();
        $shop['defсuststore'] = $this->shop->defсuststore->getValue();


        System::setOptions("shop", $shop);
        $this->setSuccess('Сохранено');
    }

    public function filterOnSubmit($sender) {

        $where = "1<>1 ";
        if ($this->listpan->filter->fdoc->isChecked()) {
            $where .= " or meta_type = 1";
        }
        if ($this->listpan->filter->fdic->isChecked()) {
            $where .= " or meta_type = 4";
        }
        if ($this->listpan->filter->frep->isChecked()) {
            $where .= " or meta_type = 2";
        }
        if ($this->listpan->filter->freg->isChecked()) {
            $where .= " or meta_type = 3";
        }
        if ($this->listpan->filter->fshop->isChecked()) {
            $where .= " or meta_type = 5";
        }


        $this->metadatads->setWhere($where);

        $this->listpan->metarow->Reload();
    }

    public function addnewOnClick($sender) {
        $this->listpan->setVisible(false);
        $this->editpan->setVisible(true);
        //   $this->editpan->editform->eipan->setVisible(false);
        $this->editpan->editform->meta_id->setText(0);
    }

    public function mcancelOnClick($sender) {
        $this->listpan->setVisible(true);
        $this->editpan->setVisible(false);
    }

    public function metarowOnRow($row) {
        $item = $row->getDataItem();
        $row->setAttribute('style', $item->disabled == 1 ? 'color: #aaa' : null);
        switch ($item->meta_type) {
            case 1:
                $title = "Документ";
                break;
            case 2:
                $title = "Отчет";
                break;
            case 3:
                $title = "Журнал";
                break;
            case 4:
                $title = "Справочник";
                break;
            case 5:
                $title = "Каталог";
                break;
        }

        $row->add(new Label('description', $item->description));
        $row->add(new Label('meta_name', $item->meta_name));
        $row->add(new Label('menugroup', $item->menugroup));
        $row->add(new Label('type', $title));
        $row->add(new ClickLink('rowedit'))->onClick($this, 'roweditOnClick');
        $row->add(new ClickLink('rowdelete'))->onClick($this, 'rowdeleteOnClick');
    }

    public function roweditOnClick($sender) {

        $item = $sender->getOwner()->getDataItem();
        $form = $this->editpan->editform;
        $form->meta_id->setText($item->meta_id);
        $form->edit_description->setText($item->description);
        $form->edit_notes->setText($item->notes);
        $form->edit_meta_name->setText($item->meta_name);
        $form->edit_menugroup->setText($item->menugroup);
        $form->edit_meta_type->setValue($item->meta_type);
        $form->edit_disabled->setChecked($item->disabled == 1);


        $this->listpan->setVisible(false);
        $this->editpan->setVisible(true);
    }

    public function rowdeleteOnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        \App\Entity\MetaData::delete($item->meta_id);

        $this->listpan->metarow->Reload();
    }

    public function editformOnSubmit($sender) {

        $meta_id = $this->editpan->editform->meta_id->getText();
        if ($meta_id > 0) {
            $item = \App\Entity\MetaData::load($meta_id);
        } else {
            $item = new \App\Entity\MetaData();
        }

        $item->description = $this->editpan->editform->edit_description->getText();
        $item->menugroup = trim($this->editpan->editform->edit_menugroup->getText());
        $item->meta_name = trim(ucfirst($this->editpan->editform->edit_meta_name->getText()));
        $item->meta_type = $this->editpan->editform->edit_meta_type->getValue();
        $item->notes = $this->editpan->editform->edit_notes->getText();
        $item->disabled = $this->editpan->editform->edit_disabled->isChecked() ? 1 : 0;


        $item->save();

        $this->listpan->setVisible(true);
        $this->editpan->setVisible(false);

        $this->listpan->metarow->Reload();
        //обнуляем  поля   формы
        $this->editpan->editform->edit_description->setText('');
        $this->editpan->editform->edit_meta_name->setText('');
        $this->editpan->editform->edit_menugroup->setText('');
        $this->editpan->editform->edit_notes->setText('');
    }

}