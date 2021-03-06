<?php

namespace App\Entity\Doc;

use \App\Entity\Entry;
use \App\Helper as H;
use \App\Util;

/**
 * Класс-сущность  документ  списание в  производство 
 *
 */
class ProdIssue extends Document {

    public function generateReport() {


        $i = 1;
        $detail = array();

        foreach ($this->unpackDetails('detaildata') as $item) {

            if (isset($detail[$item->item_id])) {
                $detail[$item->item_id]['quantity'] += $item->quantity;
            } else {
                $name = $item->itemname;
                if (strlen($item->snumber) > 0) {
                    $name .= ' (' . $item->snumber . ',' . date('d.m.Y', $item->sdate) . ')';
                }


                $detail[] = array("no" => $i++,
                    "tovar_name" => $name,
                    "tovar_code" => $item->item_code,
                    "quantity" => H::fqty($item->quantity),
                    "price" => H::fa($item->price),
                    "msr" => $item->msr,
                    "amount" => H::fa($item->quantity * $item->price)
                );
            }
        }



        $header = array('date' => date('d.m.Y', $this->document_date),
            "_detail" => $detail,
            "pareaname" => $this->headerdata["pareaname"],
            "document_number" => $this->document_number,
            "total" => H::fa($this->amount),
            "notes" => $this->notes
        );

        $report = new \App\Report('prodissue.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        $conn = \ZDB\DB::getConnect();


        foreach ($this->unpackDetails('detaildata') as $item) {
            $listst = \App\Entity\Stock::pickup($this->headerdata['store'], $item);

            foreach ($listst as $st) {
                $sc = new Entry($this->document_id, 0 - $st->quantity * $item->price, 0 - $st->quantity);
                $sc->setStock($st->stock_id);
                $sc->setExtCode($item->price - $st->partion); //Для АВС 
                $sc->save();
            }
        }

        return true;
    }

    protected function getNumberTemplate() {
        return 'СП-000000';
    }

        public function getRelationBased() {
        $list = array();
        $list['ProdIssue'] = 'Cписание в  производство';
    
        return $list;
    }
}
