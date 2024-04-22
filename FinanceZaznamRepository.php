<?php

namespace App\Model\Repositories;

use App\Model\Entities\FinanceDenik;
use App\Model\Entities\FinanceZaznam;
use App\Model\Entities\FinanceOddilyPrava;
use Doctrine\ORM\EntityManager;
use Nette\Security\User;
use App\Model\Repositories\UzivateleRepository;
use App\Model\Repositories\FinanceTypRepository;
use Nette\Utils\DateTime;
use App\Model\Repositories\FinanceDenikRepository;
use App\Model\Repositories\FinanceOddeleniRepository;
use App\Model\Repositories\StrediskaRepository;
use Matrix\Builder;

class FinanceZaznamRepository extends Repository
{
    /**
     * @var UzivateleRepository
     */
    private $uzivateleRepository;

    /**
     * @var FinanceTypRepository
     */
    public $financeTypRepository;
    /**
     * @var FInanceDenikRepository
     */
    public $financeDenikRepository;
    /**
     * @var FinanceTypPolozkyRepository
     */
    public $financeTypPolozkyRepository;
    /**
     * @var FinanceOddeleniRepository
     */
    public $financeOddeleniRepository;

    /**
     * @var FinanceOddilyPravaRepository
     */
    public $financeOddilPravaRepository;

    /**
     * @var WerkRepository
     */
    public $werkRepository;

    /**
     * @var StrediskaRepository
     * @inject
     */
    public $strediskaRepository;

    /**
     * @var User
     */
    private $user;
    private $entityManager;

    public function __construct(
        $entity,
        EntityManager $entityManager,
        \Kdyby\Translation\Translator $translator,
        \Nette\DI\Container $container,
        UzivateleRepository $uzivateleRepository,
        FinanceTypRepository $financeTypRepository,
        FinanceDenikRepository $financeDenikRepository,
        FinanceOddeleniRepository $financeOddeleniRepository,
        FinanceTypPolozkyRepository $financeTypPolozkyRepository,
        FinanceOddilyPravaRepository $financeOddilPravaRepository,
        WerkRepository $werkRepository,
        StrediskaRepository $strediskaRepository,
        User $user
    ) {
        parent::__construct($entity, $entityManager, $translator, $container);
        $this->entityManager = $entityManager;
        $this->uzivateleRepository = $uzivateleRepository;
        $this->financeTypRepository = $financeTypRepository;
        $this->financeDenikRepository = $financeDenikRepository;
        $this->financeOddeleniRepository = $financeOddeleniRepository;
        $this->financeTypPolozkyRepository = $financeTypPolozkyRepository;
        $this->financeOddilPravaRepository = $financeOddilPravaRepository;
        $this->werkRepository = $werkRepository;
        $this->strediskaRepository = $strediskaRepository;

        $this->user = $user;
    }
    public function ulozitZKatalogu($values)
    {
        $zaznam = new FinanceZaznam();
        $zaznam->setTypPolozky($this->financeTypPolozkyRepository->find($values["typPolozky"]));
        $zaznam->setFinanceOddeleni($this->financeOddeleniRepository->find($values["odd"]));
        $zaznam->setIdax($values["idax"]);
        $zaznam->setKatalogovyNazevVydaje($values["katalogovyNazevVydaje"]);
        $zaznam->setNazev($values["nazev"]);
        $zaznam->setDodavatel($values["dodavatel"]);
        $zaznam->setMnozstvi($values["mnozstvi"]);
        $zaznam->setJednotka($values["jednotka"]);
        $zaznam->setCenaKonecna($values["cenaKonecna"]);
        $zaznam->setStav(0);
        $zaznam->setDatumSchvaleni($values["datum"]);
        $zaznam->setPridal($this->uzivateleRepository->find($values["uzivatel"]));
        $zaznam->setFinanceDenik($values["denik"]);
        $zaznam->setVyzvednutoKusu(0);
        $zaznam->setDatumVlozeni(new DateTime());

        $this->save($zaznam);

        return $zaznam;
    }

    public function ulozitZaznam($idOddeleni, $values)
    {


        $zaznam = new FinanceZaznam();
        $zaznam->setIdax($values["idax"] ?? null);
        $zaznam->setTypPolozky($this->financeTypPolozkyRepository->find($values["typPolozky"]));
        $zaznam->setFinanceOddeleni($this->financeOddeleniRepository->find($idOddeleni));
        $zaznam->setKatalogovyNazevVydaje($values["katalogovyNazevVydaje"]);
        $zaznam->setNazev($values["nazev"]);
        $zaznam->setDodavatel($values["dodavatel"]);
        $zaznam->setMnozstvi($values["mnozstvi"]);
        $zaznam->setJednotka($values["jednotka"]);
        $zaznam->setCenaKonecna($values["cenaKonecna"]);
        $zaznam->setStav(0);
        $zaznam->setDatumSchvaleni($values["datum"]);
        $zaznam->setPridal($this->uzivateleRepository->find($values["uzivatel"]));
        $zaznam->setFinanceDenik($values["denik"]);
        $zaznam->setStredisko($this->strediskaRepository->find($values["stredisko"]));
        $zaznam->setVyzvednutoKusu(0);
        $zaznam->setDatumVlozeni(new DateTime());

        $this->save($zaznam);
    }
    public function editZaznam($zaznam)
    {
        $this->save($zaznam);
    }
    public function smazatZaznam($id)
    {
        $zaznam = $this->find($id);
        $this->entityManager->remove($zaznam);
        $this->entityManager->flush();
    }
    public function setPriloha($id, $priloha)
    {
        $zaznam = $this->find($id);
        $zaznam->setZaznamPriloha($priloha);

        $this->save($zaznam);
        $this->em->refresh($zaznam);
    }
    public function potvrditZaznam($id)
    {

        $zaznam = $this->find($id);
        $zaznam->setStav(1);
        $this->save($zaznam);
    }
    public function odlozitZaznam($id, $datumZmeny)
    {
        $zaznam = $this->find($id);
        $datumMin = new DateTime(date_format($datumZmeny, 'Y-m-d'));
        $datumMin->modify('first day of this month');
        $datumMax = date_modify($datumZmeny, "+1 month");
        $datumMax->modify('first day of this month');

        $denik = $this->financeDenikRepository->findDenik($datumMin, $datumMax, $zaznam->getFinanceDenik()->getWerk());

        if ($denik == []) {
            $datumNovyDenik = $datumMin;

            $this->financeDenikRepository->createDenik($datumNovyDenik, $zaznam->getFinanceDenik()->getWerk());
            $denik = $this->financeDenikRepository->findDenik($datumMin, $datumMax, $zaznam->getFinanceDenik()->getWerk());
        }
        $zaznam->setFinanceDenik($this->financeDenikRepository->find($denik[0]["ID"]));
        $this->save($zaznam);
    }
    public function getFinanceByTyp($typ, $stav = null, $denik)
    {
        $query = $this->createQueryBuilder("fin")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            ->andWhere("finOdd.id = :idTyp")
            ->setParameter("idTyp", $typ)
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik);
        if ($stav !== null) {
            $query = $query->andWhere("fin.stav = :stav")
                ->setParameter("stav", $stav);
        }
        $query = $query->orderBy("fin.id", "ASC");
        $query = $query->getQuery()->getResult();
        return $query;
    }
    public function getFinanceByTypArr($typ, $stav = null, $denik)
    {
        $query = $this->createQueryBuilder("fin")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            // ->leftJoin("fin.idax", "idax")
            ->andWhere("finOdd.id = :idTyp")
            ->setParameter("idTyp", $typ)
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik);
        if ($stav !== null) {
            $query = $query->andWhere("fin.stav = :stav")
                ->setParameter("stav", $stav);
        }
        $query = $query->orderBy("fin.id", "ASC");
        $zaznamy = $query->getQuery()->getResult();

        $zazanmyArr = array();
        foreach ($zaznamy as $zaznam) {
            $zazanmyArr[] = $zaznam->toArray();
        }
        bdump($zazanmyArr);
        return $zazanmyArr;
    }

    public function getFinanceCSV($stav = null, $denik, $datum, $prava)
    {
        $d = new DateTime($datum->format("Y-m-d"));
        $d->modify("-1 day");
        $dEnd = new DateTime($datum->format("Y-m-d"));
        $dEnd->modify("+1 month");
        $query = $this->createQueryBuilder("fin")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            ->orderBy("finOdd.id", "ASC")
            ->andWhere("finDenik.datumZacatkuDeniku >= :datumZacatek")
            ->setParameter("datumZacatek", $d)
            ->andWhere("finDenik.datumZacatkuDeniku <= :datumKonec")
            ->setParameter(":datumKonec", $dEnd)
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik)
            ->andWhere("finOdd.id IN (:prav)")
            ->setParameter("prav", $prava);
        if ($stav !== null) {

            $query = $query->andWhere("fin.stav = :stav")
                ->setParameter("stav", $stav);
        }
        $query = $query->orderBy("fin.id", "ASC");
        $query = $query->getQuery()->getResult();
        return $query;
    }
    public function getFinanceSoucet($stav, $denik)
    {

        $query = $this->createQueryBuilder("fin")
            ->select("SUM(fin.cenaKonecna) as cenaKonecna")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik);
        if ($stav !== null) {

            $query = $query->andWhere("fin.stav = :stav")
                ->setParameter("stav", $stav);
        }
        $query = $query->getQuery()->getResult();
        return $query;
    }

    public function getFinanceSoucetVidene($stav, $denik, $prava)
    {

        $query = $this->createQueryBuilder("fin")
            ->select("SUM(fin.cenaKonecna) as cenaKonecnaVidene")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik)
            ->andWhere("finOdd.id IN (:prava)")
            ->setParameter("prava", $prava);
        if ($stav !== null) {
            $query = $query->andWhere("fin.stav = :stav")
                ->setParameter("stav", $stav);
        }

        $query = $query->getQuery()->getResult();
        return $query;
    }

    public function getFinanceObjednane($denik, $prava = null, $vratitSoucet = false)
    {

        $query = $this->createQueryBuilder("fin")
            ->join("fin.financeOddeleni", "finOdd")
            ->join("fin.financeDenik", "finDenik")
            ->andWhere("finDenik.id = :idDenik")
            ->setParameter("idDenik", $denik)
            ->andWhere("fin.stav = :stav")
            ->setParameter("stav", 1);
        if ($prava != null) {
            $query = $query
                ->andWhere("finOdd.id IN (:prava)")
                ->setParameter("prava", $prava);
        }

        $query = $query->orderBy("fin.id", "ASC");
        $query = $query->getQuery()->getResult();

        $sum = 0;
        $vys = [];
        foreach ($query as $value) {
            if (is_numeric($value->getObjednavka())) {
                $vys[] = $value;
                $sum += $value->getCenaKonecna();
            }
        }
        return $vratitSoucet ? $sum : $vys;
    }

    public function getFinanceProStat($datumOd, $datumDo)
    {
        $datumOd = $datumOd->modify("first day of this month");
        $datumDo = $datumDo->modify("last day of this month");

        $qb = $this->createQueryBuilder("fin")
            ->select("YEAR(fin.datumSchvaleni) as rok, MONTH(fin.datumSchvaleni) AS mesic, sum(fin.cenaKonecna) as pocet")
            ->where("fin.datumSchvaleni >= :datOd")
            ->andWhere("fin.datumSchvaleni <= :datDo")
            ->andWhere("fin.stav = :stav");

        $qb->groupBy("rok, mesic")
            ->orderBy("rok", "ASC")
            ->addOrderBy("mesic", "ASC")
            ->setParameter("datOd", $datumOd)
            ->setParameter("datDo", $datumDo)
            ->setParameter("stav", 1);

        $qb2 = clone $qb;
        $qb2->andWhere("fin.objednavka IS NOT NULL")
            ->andWhere("fin.objednavka != 'p'")
            ->andWhere("fin.objednavka != 'P'");

        $vys = $qb->getQuery()->getResult();
        $vys2 = $qb2->getQuery()->getResult();

        $mesice = [];
        $help = [];

        foreach ($vys as $key => $value) {
            $help[$value["mesic"] . "." . $value["rok"]] = ["celkem" => intval($value["pocet"]), "objednane" => 0];
        }
        foreach ($vys2 as $key => $value) {
            $help[$value["mesic"] . "." . $value["rok"]]["objednane"] = intval($value["pocet"]);
        }

        foreach ($help as $key => $value) {
            $mesice[] = ["Mesic" => $key, "Celkem" => $value["celkem"], "Objednane" => $value["objednane"]];
        }

        return $mesice;
    }

    public function getAllZaPosledniRok()
    {
        $userPravaArr = $this->financeOddilPravaRepository->findProUzivatele($this->user, FinanceOddilyPrava::MESICNI_PLAN);

        $q = $this->createQueryBuilder("fin")
            ->select("fin, oddel, finDenik, werk")
            ->leftJoin("fin.financeOddeleni", "oddel")
            ->leftJoin("fin.financeDenik", "finDenik")
            ->leftJoin("finDenik.werk", "werk")
            ->where("oddel.id IN (:odd)")
            ->andWhere("fin.datumSchvaleni >= :dat")
            ->orderBy("finDenik.datumZacatkuDeniku", "desc")
            ->setParameter("odd", $userPravaArr["Edit"])
            ->setParameter("dat", (new DateTime())->modify("-1 year"));
        $res = $q->getQuery()->getArrayResult();

        foreach ($res as $r => $arr) {
            $res[$r]["oddNaz"] = $arr["financeOddeleni"]["nazev"];
            $res[$r]["oddId"] = $arr["financeOddeleni"]["id"];
            $res[$r]["datumZacatkuDeniku"] = date_format($arr["financeDenik"]["datumZacatkuDeniku"], "m.Y");
            $res[$r]["denikId"] = $arr["financeDenik"]["id"];
            // $res[$r]["werk"] = $this->findOneBy(["id" => $arr["financeDenik"]["id"]]);
        }

        return $res;
    }

    public function copyProNovyDenik(FinanceZaznam $zaznam, $idNove, $copyCele = false, $datumZacatkuDeniku = null)
    {
        $zaz = new FinanceZaznam;
        $zaz->setTypPolozky($zaznam->getTypPolozky());
        $zaz->setKatalogovyNazevVydaje($zaznam->getKatalogovyNazevVydaje());
        $zaz->setNazev($zaznam->getNazev());
        $zaz->setPridal($this->uzivateleRepository->find($this->user->id));
        $zaz->setDodavatel($zaznam->getDodavatel());
        $zaz->setFinanceOddeleni($this->financeOddeleniRepository->find($idNove));
        $zaz->setDatumVlozeni(new DateTime());
        $zaz->setStredisko($zaznam->getStredisko());
        $zaz->setVyzvednutoKusu(0);
        $zaz->setJednotka($zaznam->getJednotka());
        $zaz->setIdax($zaznam->getIdax());

        if($datumZacatkuDeniku == null) {
            $datumZacatkuDeniku = (new DateTime())->modify("first day of next month");
        }
        
        $denik = $this->financeDenikRepository->findBy(["datumZacatkuDeniku" => $datumZacatkuDeniku, "werk" => $this->user->getIdentity()->werk]);

        if ($denik != null) {
            $zaz->setFinanceDenik($denik[0]);
        } else {
            $denik = new FinanceDenik;
            $denik->setWerk($this->werkRepository->find($this->user->getIdentity()->werk));
            $denik->setDatumZacatkuDeniku($datumZacatkuDeniku);
            $denik->setZamknuty(0);
            $this->financeDenikRepository->em->persist($denik);
            $this->financeDenikRepository->em->flush($denik);

            $zaz->setFinanceDenik($denik);
        }

        if($copyCele){
            $zaz->setMnozstvi($zaznam->getMnozstvi());
            $zaz->setCenaKonecna($zaznam->getCenaKonecna());
        }

        $zaz->setDatumSchvaleni($datumZacatkuDeniku->modify("+1 month"));
        $zaz->setStav(0);

        $this->em->persist($zaz);
        $this->em->flush($zaz);

        return $zaz;
    }

    public function presunNeschvalenychDoDalsihoDeniku($denikId)
    {
        $denik = $this->financeDenikRepository->find($denikId);
        $datumZacatkuDeniku = $denik->getDatumZacatkuDeniku()->modify("first day of next month");
        $novyDenik = $this->financeDenikRepository->findOneBy(["datumZacatkuDeniku" => $datumZacatkuDeniku, "werk" => $denik->getWerk()]);
        if ($novyDenik == null) {
            $novyDenik = $this->financeDenikRepository->createDenik($datumZacatkuDeniku, $denik->getWerk());
        }
        $zaznamy = $this->findby(["financeDenik" => $denikId, "stav" => 0]);
        foreach($zaznamy as $zaznam) {
            $zaznam->setFinanceDenik($novyDenik);
            $this->save($zaznam);
        }
    }
}
