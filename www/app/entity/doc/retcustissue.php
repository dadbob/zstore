<?php

namespace App\Entity\Doc;

use \App\Entity\Entry;
use \App\Helper as H;
use \App\Util;

/**
 * Класс-сущность  документ возврат  поставщику
 *
 */
class RetCustIssue extends Document {

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
                    "msr" => $item->msr,
                    "price" => H::fa($item->price),
                    "amount" => H::fa($item->quantity * $item->price)
                );
            }
        }


        $customer = \App\Entity\Customer::load($this->customer_id);

        $header = array('date' => date('d.m.Y', $this->document_date),
            "_detail" => $detail,
            "firmname" => $this->headerdata["firmname"],
            "customer_name" => $this->headerdata["customer_name"],
            "document_number" => $this->document_number,
            "total" => $this->amount
        );


        $report = new \App\Report('retcustissue.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        $conn = \ZDB\DB::getConnect();


        foreach ($this->unpackDetails('detaildata') as $item) {

            $sc = new Entry($this->document_id, 0 - $item->amount, 0 - $item->quantity);
            $sc->setStock($item->stock_id);
            $sc->setExtCode(0 - $item->amount); //Для АВС 

            $sc->save();
        }
        if ($this->headerdata['payment'] > 0) {
            \App\Entity\Pay::addPayment($this->document_id, $this->amount, $this->headerdata['payment'], \App\Entity\Pay::PAY_BASE_INCOME);
            $this->payamount = $this->amount;
        }


        return true;
    }

    protected function getNumberTemplate() {
        return 'ВП-000000';
    }

}
