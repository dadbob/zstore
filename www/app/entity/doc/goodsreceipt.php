<?php

namespace App\Entity\Doc;

use \App\Entity\Entry;
use \App\Helper as H;

/**
 * Класс-сущность  документ приходная  накладая
 *
 */
class GoodsReceipt extends Document {

    public function generateReport() {


        $i = 1;

        $detail = array();
        foreach ($this->unpackDetails('detaildata') as $item) {
            $name = $item->itemname;
            if (strlen($item->snumber) > 0) {
                $name .= ' (' . $item->snumber . ',' . date('d.m.Y', $item->sdate) . ')';
            }

            $detail[] = array("no" => $i++,
                "itemname" => $name,
                "snumber" => $item->snumber,
                "itemcode" => $item->item_code,
                "quantity" => H::fqty($item->quantity),
                "price" => H::fa($item->price),
                "msr" => $item->msr,
                "amount" => H::fa($item->quantity * $item->price)
            );
        }

        $header = array('date' => date('d.m.Y', $this->document_date),
            "_detail" => $detail,
            "basedoc" => $this->headerdata["basedoc"],
            "customer_name" => $this->headerdata["customer_name"],
            "document_number" => $this->document_number,
            "total" => H::fa($this->amount),
            "payed" => H::fa($this->payed),
            "prepaid" => $this->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID,
            "payamount" => H::fa($this->payamount)
        );


        $report = new \App\Report('goodsreceipt.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        $types = array();
        $common = \App\System::getOptions("common");

        //аналитика
        foreach ($this->unpackDetails('detaildata') as $item) {
            $stock = \App\Entity\Stock::getStock($this->headerdata['store'], $item->item_id, $item->price, $item->snumber, $item->sdate, true);


            $sc = new Entry($this->document_id, $item->amount, $item->quantity);
            $sc->setStock($stock->stock_id);
            $sc->setExtCode($item->amount); //Для АВС 
            // $sc->setCustomer($this->customer_id);

            $sc->save();


            if ($common['useval'] == true) {
                // if($row['old']==true)continue;  //не  меняем для  предыдущих строк
                //запоминаем курс  последней покупки
                $it = \App\Entity\Item::load($item->item_id);
                $it->curname = $item->curname;
                $it->currate = $item->currate;
                $it->save();
            }
        }


        if ($this->headerdata['payment'] > 0 && $this->payed > 0) {
            \App\Entity\Pay::addPayment($this->document_id, 0 - $this->payed, $this->headerdata['payment'], \App\Entity\Pay::PAY_BASE_OUTCOME);
        }


        return true;
    }

    protected function getNumberTemplate() {
        return 'ПН-000000';
    }

    public function getRelationBased() {
        $list = array();

        $list['RetCustIssue'] = 'Возврат  поставщику';
        $list['GoodsReceipt'] = 'Приходная  накладная';

        return $list;
    }

}
