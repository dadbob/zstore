<?php

namespace App\Entity\Doc;

use \App\Entity\Entry;
use \App\Entity\Stock;
use \App\Entity\Store;
use \App\Helper as H;

/**
 * Класс-сущность  локумент перемещения товаров
 *
 */
class MoveItem extends Document {

    public function Execute() {



        $conn = \ZDB\DB::getConnect();
        $conn->StartTrans();


        foreach ($this->detaildata as $value) {

            //списываем  со склада
            $stockfrom = $value['stock_id'];
            $sc = new Entry($this->document_id, 0 - ($value['quantity'] * $value['partion']), 0 - $value['quantity']);
            $sc->setStock($stockfrom);


            $sc->save();

            $stockto = Stock::getStock($this->headerdata['storeto'], $value['item_id'], $value['partion'],$value['snumber'],$value['sdate'], true);
            $sc = new Entry($this->document_id, $value['quantity'] * $value['partion'], $value['quantity']);
            $sc->setStock($stockto->stock_id);


            $sc->save();
        }


        $conn->CompleteTrans();
        return true;
    }

    public function generateReport() {





        $i = 1;
        $detail = array();
        foreach ($this->detaildata as $value) {
            $name =$value['itemname'];
            if(strlen($value['snumber'])>0){
               $name .= ' ('.$value['snumber'].','.date('Y-m-d',$value['sdate']).')';  
            }
            
            $detail[] = array("no" => $i++,
                "item_name" => $name,
                "price" => $value['partion'],
                "msr" => $value['msr'],
                "quantity" => H::fqty($value['quantity']));
        }

        $header = array(
            "_detail" => $detail,
            'date' => date('d.m.Y', $this->document_date),
            "from" => Store::load($this->headerdata["storefrom"])->storename,
            "to" => Store::load($this->headerdata["storeto"])->storename,
            "document_number" => $this->document_number
        );
        $report = new \App\Report('moveitem.tpl');

        $html = $report->generate($header);

        return $html;
    }

}
