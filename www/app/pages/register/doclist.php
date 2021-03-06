<?php

namespace App\Pages\Register;

use \Zippy\Html\DataList\DataView;
use \Zippy\Html\DataList\Paginator;
use \Zippy\Html\Form\CheckBox;
use \Zippy\Html\Form\Date;
use \Zippy\Html\Form\DropDownChoice;
use \Zippy\Html\Form\Form;
use \Zippy\Html\Form\TextInput;
use \Zippy\Html\Form\SubmitButton;
use \Zippy\Html\Panel;
use \Zippy\Html\Label;
use \Zippy\Html\Link\ClickLink;
use \Zippy\Html\Link\SortLink;
use \App\Entity\Doc\Document;
use \App\Entity\Customer;
use \Zippy\Html\Form\AutocompleteTextInput;
use \App\Filter;
use \App\Helper as H;
use \App\Application as App;
use \App\System;

/**
 * журнал  докуметов
 */
class DocList extends \App\Pages\Base {

    public $_doc;

    /**
     *
     * @param mixed $docid Документ  должен  быть  показан  в  просмотре
     * @return DocList
     */
    public function __construct($docid = 0) {
        parent::__construct();
        if (false == \App\ACL::checkShowReg('DocList'))
            return;


        $filter = Filter::getFilter("doclist");
        if ($filter->to == null) {
            $filter->to = time() + (3 * 24 * 3600);
            $filter->from = time() - (7 * 24 * 3600);
            $filter->page = 1;
            $filter->doctype = 0;
            $filter->customer = 0;
            $filter->customer_name = '';

            $filter->searchnumber = '';
        }
        $this->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');
        $this->filter->add(new Date('from', $filter->from));
        $this->filter->add(new Date('to', $filter->to));
        $this->filter->add(new DropDownChoice('doctype', H::getDocTypes(), $filter->doctype));

        $this->filter->add(new ClickLink('erase', $this, "onErase"));
        $this->filter->add(new AutocompleteTextInput('searchcust'))->onText($this, 'OnAutoCustomer');
        $this->filter->searchcust->setKey($filter->customer);
        $this->filter->searchcust->setText($filter->customer_name);
        $this->filter->add(new TextInput('searchnumber', $filter->searchnumber));

        if (strlen($filter->docgroup) > 0)
            $this->filter->docgroup->setValue($filter->docgroup);


        $this->add(new SortLink("sortdoc", "meta_desc", $this, "onSort"));
        $this->add(new SortLink("sortnum", "document_number", $this, "onSort"));
        $this->add(new SortLink("sortdate", "document_id", $this, "onSort"));
        $this->add(new SortLink("sortcust", "customer_name", $this, "onSort"));
        $this->add(new SortLink("sortamount", "amount", $this, "onSort"));
        $this->add(new SortLink("sortstatus", "state", $this, "onSort"));


        $doclist = $this->add(new DataView('doclist', new DocDataSource(), $this, 'doclistOnRow'));
        $doclist->setSelectedClass('table-success');

        $this->add(new Paginator('pag', $doclist));
        $doclist->setPageSize(H::getPG());
        $filter->page = $this->doclist->setCurrentPage($filter->page);
        $this->doclist->setSorting('document_id', 'desc');
        $doclist->Reload();
        $this->add(new \App\Widgets\DocView('docview'))->setVisible(false);
        if ($docid > 0) {
            $this->docview->setVisible(true);
            $dc = Document::load($docid);
            $this->docview->setDoc($dc);
            //$this->doclist->setSelectedRow($docid);
            $filter->searchnumber = $dc->document_number;
            $this->filter->searchnumber->setText($dc->document_number);
            $doclist->Reload();
        }
        $this->add(new Form('statusform'))->SetVisible(false);
        ;
        $this->statusform->add(new SubmitButton('bap'))->onClick($this, 'statusOnSubmit');
        $this->statusform->add(new SubmitButton('bref'))->onClick($this, 'statusOnSubmit');
        $this->statusform->add(new TextInput('refcomment'));

        $this->add(new ClickLink('csv', $this, 'oncsv'));
    }

    public function onErase($sender) {
        $filter = Filter::getFilter("doclist");
        $filter->to = time();
        $filter->from = time() - (7 * 24 * 3600);
        $filter->page = 1;
        $filter->doctype = 0;
        $filter->customer = 0;
        $filter->customer_name = '';

        $filter->searchnumber = '';

        $this->filter->clean();
        $this->filter->to->setDate(time());
        ;
        $this->filter->from->setDate(time() - (7 * 24 * 3600));
        ;
        $this->filterOnSubmit($this->filter);
        ;
    }

    public function filterOnSubmit($sender) {

        $this->docview->setVisible(false);
        //запоминаем  форму   фильтра
        $filter = Filter::getFilter("doclist");
        $filter->from = $this->filter->from->getDate();
        $filter->to = $this->filter->to->getDate(true);
        $filter->doctype = $this->filter->doctype->getValue();
        $filter->customer = $this->filter->searchcust->getKey();
        $filter->customer_name = $this->filter->searchcust->getText();


        $filter->searchnumber = trim($this->filter->searchnumber->getText());

        $this->doclist->setCurrentPage(1);
        //$this->doclist->setPageSize($this->filter->rowscnt->getValue());

        $this->doclist->Reload();
    }

    public function doclistOnRow($row) {
        $doc = $row->getDataItem();
        $doc = $doc->cast();
        $row->add(new Label('name', $doc->meta_desc));
        $row->add(new Label('number', $doc->document_number));

        $row->add(new Label('cust', $doc->customer_name));
        $row->add(new Label('branch', $doc->branch_name));
        $row->add(new Label('date', date('d-m-Y', $doc->document_date)));
        $row->add(new Label('amount', H::fa(($doc->payamount > 0) ? $doc->payamount : ($doc->amount > 0 ? $doc->amount : "" ))));

        $row->add(new Label('state', Document::getStateName($doc->state)));
        $row->add(new Label('waitpay'))->setVisible($doc->payamount > 0 && $doc->payamount > $doc->payed);
        $row->add(new Label('waitapp'))->setVisible($doc->state == Document::STATE_WA);

        $date = new \Carbon\Carbon();
        $date = $date->addDay(1);
        $start = $date->startOfDay()->timestamp;
        $row->add(new Label('isplanned'))->setVisible($doc->document_date >= $start);

        $row->add(new Label('hasnotes'))->setVisible(strlen($doc->notes) > 0 && $doc->notes == strip_tags($doc->notes));
        $row->hasnotes->setAttribute('title', $doc->notes);

        $row->add(new ClickLink('parentdoc', $this, 'basedOnClick'))->setVisible($doc->parent_id > 0);
        $row->parentdoc->setValue($doc->headerdata['parent_number']);

        $row->add(new ClickLink('show'))->onClick($this, 'showOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->add(new ClickLink('cancel'))->onClick($this, 'cancelOnClick');
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');

        //список документов   которые   могут  быть созданы  на  основании  текущего
        $row->add(new Panel('basedon'));
        $basedonlist = $doc->getRelationBased();
        if (count($basedonlist) == 0) {
            $row->basedon->setVisible(false);
        } else {
            $list = "";
            foreach ($basedonlist as $doctype => $docname) {
                $list .= "<a  class=\"dropdown-item\" href=\"/index.php?p=App/Pages/Doc/" . $doctype . "&arg=/0/{$doc->document_id}\">{$docname}</a>";
            };
            $row->basedon->add(new Label('basedlist'))->setText($list, true);
        }

        if ($doc->state == Document::STATE_WA) {  //ждем  подтвержения
            $row->basedon->setVisible(false);
        }

        if ($doc->state < Document::STATE_EXECUTED) {
            $row->edit->setVisible(true);
            $row->delete->setVisible(true);
            $row->cancel->setVisible(false);
            $row->waitpay->setVisible(false);

            $row->isplanned->setVisible(false);
            $row->basedon->setVisible(false);
        } else {
            $row->edit->setVisible(false);
            $row->delete->setVisible(false);
            $row->cancel->setVisible(true);
        }
    }

    public function onSort($sender) {
        $sortfield = $sender->fileld;
        $sortdir = $sender->dir;

        $this->sortdoc->Reset();
        $this->sortnum->Reset();
        $this->sortdate->Reset();
        $this->sortcust->Reset();
        $this->sortamount->Reset();
        $this->sortstatus->Reset();


        $this->doclist->setSorting($sortfield, $sortdir);


        $sender->fileld = $sortfield;
        $sender->dir = $sortdir;
        $this->doclist->Reload();
    }

    //просмотр

    public function basedOnClick($sender) {
        $doc = $sender->getOwner()->getDataItem();
        $parent = Document::load($doc->parent_id);

        $this->show($parent);
    }

    public function showOnClick($sender) {
        $doc = $sender->getOwner()->getDataItem();
        $this->doclist->setSelectedRow($sender->getOwner());
        $this->show($doc);
    }

    public function show($doc) {
        $this->_doc = $doc;
        if (false == \App\ACL::checkShowDoc($this->_doc, true))
            return;

        $this->docview->setVisible(true);
        $this->docview->setDoc($this->_doc);

        $this->doclist->Reload(false);
        $this->goAnkor('dankor');
        $this->statusform->setVisible($this->_doc->state == Document::STATE_WA);
    }

    //редактирование
    public function editOnClick($sender) {
        $item = $sender->owner->getDataItem();
        if (false == \App\ACL::checkEditDoc($item, true))
            return;
        $type = H::getMetaType($item->meta_id);
        $class = "\\App\\Pages\\Doc\\" . $type['meta_name'];
        //   $item = $class::load($item->document_id);
        //запоминаем страницу пагинатора
        $filter = Filter::getFilter("doclist");
        $filter->page = $this->doclist->getCurrentPage();

        App::Redirect($class, $item->document_id);
    }

    public function deleteOnClick($sender) {
        $this->docview->setVisible(false);

        $doc = $sender->owner->getDataItem();
        if (false == \App\ACL::checkEditDoc($doc, true))
            return;


        $user = System::getUser();
        if ($doc->user_id != $user->user_id && $user->userlogin != 'admin') {
            $this->setError("Удалять документ  может  только  автор или администратор");
            return;
        }
        $f = $doc->checkStates(array(Document::STATE_INSHIPMENT, Document::STATE_DELIVERED));
        if ($f) {
            $this->setError("У документа были отправки или доставки");
            return;
        }

        $list = $doc->getChildren();
        if (count($list) > 0) {
            $this->setError("У документа есть дочерние документы");
            return;
        }

        $del = Document::delete($doc->document_id);
        if (strlen($del) > 0) {
            $this->setError($del);
            return;
        }
        $this->doclist->Reload(true);
        $this->resetURL();
    }

    public function cancelOnClick($sender) {
        $this->docview->setVisible(false);

        $doc = $sender->owner->getDataItem();
        //   if (false == \App\ACL::checkEditDoc($doc, true))
        //     return;
        $user = System::getUser();

        if (\App\ACL::checkExeDoc($doc, true, false) == false) {
            if ($doc->state == Document::STATE_WA && $doc->user_id == $user->user_id) {
                //свой может  отменить
            } else {
                $this->setError('Нет  права отменять документ ' . $doc->meta_desc);
                return;
            }
        }


        $f = $doc->checkStates(array(Document::STATE_CLOSED, Document::STATE_INSHIPMENT, Document::STATE_DELIVERED));
        if ($f) {
            System::setWarnMsg("У документа были отправки, доставки или документ был  закрыт");
        }
        $list = $doc->getChildren('', true);
        if (count($list) > 0) {
            $this->setError("У документа есть неотмененные дочерние документы");
            return;
        }

        $doc->updateStatus(Document::STATE_CANCELED);
        $this->doclist->setSelectedRow($sender->getOwner());
        $this->doclist->Reload(false);
        $this->resetURL();
    }

    public function OnAutoCustomer($sender) {
        $text = Customer::qstr('%' . $sender->getText() . '%');
        return Customer::findArray("customer_name", "status=0 and (customer_name like {$text}  or phone like {$text} )");
    }

    public function statusOnSubmit($sender) {
        if (\App\ACL::checkExeDoc($this->_doc, true, false) == false) {
            $this->setError('Нет  права выполнять документ ');
            return;
        }
        $this->_doc= $this->_doc->cast();
        if ($sender->id == "bap") {
            $newstate = $this->_doc->headerdata['_state_before_approve_'] > 0 ? $this->_doc->headerdata['_state_before_approve_'] : Document::STATE_APPROVED;
            $this->_doc->updateStatus($newstate);


            $user = System::getUser();

            $n = new \App\Entity\Notify();
            $n->user_id = $this->_doc->user_id;
            $n->dateshow = time();
            $n->message = "Пользователь <b>{$user->username}</b>  утвердил документ " . $this->_doc->document_number;
            $n->save();
        }
        if ($sender->id == "bref") {
            $this->_doc->updateStatus(Document::STATE_REFUSED);

            $text = trim($this->statusform->refcomment->getText());

            $user = System::getUser();

            $n = new \App\Entity\Notify();
            $n->user_id = $this->_doc->user_id;
            $n->dateshow = time();
            $n->message = "Пользователь <b>{$user->username}</b>  отклонил документ " . $this->_doc->document_number;
            $n->message .= "<br> " . $text;
            $n->save();

            $this->statusform->refcomment->setText('');
        }

        $this->statusform->setVisible(false);
        $this->docview->setVisible(false);
        $this->doclist->Reload(false);
    }

    public function oncsv($sender) {
        $list = $this->doclist->getDataSource()->getItems(-1, -1, 'document_id');
        $csv = "";

        foreach ($list as $d) {
            $csv .= date('Y.m.d', $d->document_date) . ';';
            $csv .= $d->document_number . ';';
            $csv .= $d->meta_desc . ';';
            $csv .= $d->customer_name . ';';
            $csv .= $d->amount . ';';
            $csv .= str_replace(';', '', $d->notes) . ';';
            $csv .= "\n";
        }
        $csv = mb_convert_encoding($csv, "windows-1251", "utf-8");


        header("Content-type: text/csv");
        header("Content-Disposition: attachment;Filename=doclist.csv");
        header("Content-Transfer-Encoding: binary");

        echo $csv;
        flush();
        die;
    }

}

/**
 *  Источник  данных  для   списка  документов
 */
class DocDataSource implements \Zippy\Interfaces\DataSource {

    private function getWhere() {
        $user = System::getUser();

        $conn = \ZDB\DB::getConnect();
        $filter = Filter::getFilter("doclist");
        $where = " date(document_date) >= " . $conn->DBDate($filter->from) . " and  date(document_date) <= " . $conn->DBDate($filter->to);

        if ($filter->doctype > 0) {
            $where .= " and meta_id  ={$filter->doctype} ";
        }
        if ($filter->customer > 0) {
            $where .= " and customer_id  ={$filter->customer} ";
        }

        $sn = $filter->searchnumber;

        if (strlen($sn) > 1) {
            // игнорируем другие поля
            $sn = $conn->qstr('%' . $sn . '%');
            $where = "    document_number like  {$sn} ";
        }



        return $where;
    }

    public function getItemCount() {
        return Document::findCnt($this->getWhere());
    }

    public function getItems($start, $count, $sortfield = null, $asc = null) {
        $docs = Document::find($this->getWhere(), $sortfield . " " . $asc, $count, $start);

        //$l = Traversable::from($docs);
        //$l = $l->where(function ($doc) {return $doc->document_id == 169; }) ;
        //$l = $l->select(function ($doc) { return $doc; })->asArray() ;
        return $docs;
    }

    public function getItem($id) {
        
    }

}
