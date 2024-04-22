<?php

namespace App\ControllingModule\Presenters;

use App\Presenters\BaseSecuredPresenter;
use Ublaboo\DataGrid\DataGrid;
use Kdyby\Translation\Translator;
use App\Model\Repositories\FinanceZaznamRepository;
use Nette\Forms\Container;
use Nette\Utils\DateTime;
use Nette\Utils\ArrayHash;
use App\Model\Repositories\FinanceTypRepository;
use Nette\Application\UI\Multiplier;
use Nette\Application\UI\Form;
use App\Lib\BootstrapV4Renderer;
use App\Model\Entities\FinanceOddilyPrava;
use App\Model\Entities\FinanceZaznam;
use App\Model\Entities\TypNotifikace;
use App\Model\EnumJednotky;
use App\Model\Repositories\FinanceDenikRepository;
use App\Model\Repositories\WerkRepository;
use App\Model\Repositories\FinanceOddeleniRepository;
use App\Model\Repositories\FinanceOddilyPravaRepository;
use App\Model\Repositories\FinanceTypPolozkyRepository;
use App\Model\Repositories\FinanceKatalogRepository;
use App\Model\Repositories\NotifikaceRepository;
use App\Model\Repositories\UzivateleRepository;
use App\Model\Repositories\StrediskaRepository;
use App\Model\Entities\FinanceKatalog;
use DateTimeImmutable;
use Exception;
use Nette\Application\Responses\FileResponse;
use Nette\Utils\FileSystem;
use Nette\Utils\Html;

class FinancePrehledPresenter extends BaseSecuredPresenter
{

    /** 
     * @var FinanceZaznamRepository 
     * @inject 
     */
    public $financeZaznamRepository;

    /** 
     * @var FinanceTypRepository 
     * @inject */
    public $financeTypRepository;

    /** 
     * @var FinanceDenikRepository 
     * @inject */
    public $financeDenikRepository;

    /** 
     * @var FinanceKatalogRepository 
     * @inject */
    public $financeKatalogRepository;

    /** 
     * @var WerkRepository 
     * @inject */
    public $werkRepository;

    /**
     * @var FinanceOddeleniRepository
     * @inject */
    public $financeOddeleniRepository;

    /**
     * @var FinanceTypPolozkyRepository
     * @inject
     */
    public $financeTypPolozkyRepository;

    /**
     * @var FinanceOddilyPravaRepository
     * @inject
     */
    public $financeOddilPravaRepository;

    /**
     * @var NotifikaceRepository
     * @inject
     */
    public $notifikaceRepository;

    /**
     * @var UzivateleRepository
     * @inject
     */
    public $uzivateleRepository;

    /**
     * @var StrediskaRepository
     * @inject
     */
    public $strediskaRepository;

    public $translator;

    /**
     * @persistent
     */
    public $stav;


    private $denik;

    private $pravaOddily;

    /**
     * @persistent
     */
    public $datum;

    public $date;

    private $denikVytvoreny;

    private $proVydej = [];

    private $pridatDoKatalogu = null;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function beforeRender()
    {
        parent::beforeRender();

        $financeArr = $this->financeOddeleniRepository->getPairArray("getId", "getNazev");
        
        $this->template->typyFinance = $financeArr;

        if ($this->denikVytvoreny == true) {
            $celkem = $this->financeZaznamRepository->getFinanceSoucet($this->stav, $this->denik)[0];
            $celkemObjednane = $this->financeZaznamRepository->getFinanceObjednane($this->denik, null, true);

            $celkemVidene = $this->financeZaznamRepository->getFinanceSoucetVidene($this->stav, $this->denik, $this->pravaOddily["All"])[0];
            $celkemVideneObjednane = $this->financeZaznamRepository->getFinanceObjednane($this->denik, $this->pravaOddily["All"], true);

            $this->template->cenaKonecna = $celkem["cenaKonecna"] == 0 ? 0 : number_format($celkem["cenaKonecna"], 0, ".", " ");
            $this->template->cenaKonecnaObjednane = $celkemObjednane == 0 ? 0 : number_format($celkemObjednane, 0, ".", " ");

            $this->template->cenaKonecnaVidene = $celkemVidene["cenaKonecnaVidene"] == 0 ? 0 : number_format($celkemVidene["cenaKonecnaVidene"], 0, ".", " ");
            $this->template->cenaKonecnaVideneObjednane = $celkemVideneObjednane == 0 ? 0 : number_format($celkemVideneObjednane, 0, ".", " ");

            $this->template->datum = date_format($this->denik->getDatumZacatkuDeniku(), "m.Y");
            $this->template->stavDeniku = $this->denik->getZamknuty() == 1 ? $this->translator->translate('finance.odemknout') : $this->translator->translate('finance.zamknout');
            $this->template->zamceno = $this->denik->getZamknuty();
        } else {
            $this->template->datum = date_format(new DateTime($this->date), "m.Y");
            $this->template->stavDeniku = null;
        }
        $this->template->denikVytvoreny = $this->denikVytvoreny;
        $this->template->prava = $this->pravaOddily;
        $this->template->editor = $this->pravaOddily["Edit"] != null;
        $this->template->typDeniku = FinanceOddilyPrava::MESICNI_PLAN;
        $this->template->pridatDoKatalogu = $this->pridatDoKatalogu;
    }

    public function actionDefault($stav = null, $denik = null, $datum = null)
    {
        $denik = null;

        $this->stav = $stav;
        $datumMin = null;
        $datumMax = null;

        if ($denik != null) {
            $this->denik = $this->financeDenikRepository->find($denik);
            //Pokud se našel deník s jiným werkem než je zvolený, nastavit ho na null
            if ($this->denik->getWerk()->getId() != $this->user->getIdentity()->werk) {
                $this->denik = null;
            }
        }



        $date = null;

        if ($datum === null) {
            $datumMin = new DateTime();
            $datumMin->modify('first day of next month');
            $date = $datumMin;

            $datumMax = new DateTime(date_format($datumMin, 'Y-m-d'));
            $datumMax->modify("+1 month");
            $datumMax->modify('first day of this month');
        } else {
            $datumMin = new DateTime();
            $datumMin = $datumMin->createFromFormat("Y-m-d", $datum);
            $datumMin->modify('first day of this month');
            $date = $datumMin;

            $datumMax = new DateTime(date_format($datumMin, 'Y-m-d'));
            $datumMax->modify("+1 month");
            $datumMax->modify('first day of this month');
        }

        if ($this->denik !== null) {
            if ($this->denik->getDatumZacatkuDeniku()->modify('first day of this month') == $datumMin) {
                $this->denik = null;
            }
        }

        if ($denik == null) {
            $idDenik = $this->financeDenikRepository->findDenik($datumMin, $datumMax, $this->user->getIdentity()->werk);
            if ($idDenik != []) {
                $this->denik = $this->financeDenikRepository->find($idDenik[0]["ID"]);
            }
        }

        $this->datum = $datum;
        $this->date = $date;

        $this->denikVytvoreny = $this->denik !== null;

        $userPravaArr = $this->financeOddilPravaRepository->findProUzivatele($this->user, financeOddilyPrava::MESICNI_PLAN);

        $this->pravaOddily = $userPravaArr;

        if ($this->isAjax()) {
            $this->redrawControl("summary");
        }


        //check notifikace a případné přečtení
        if ($this->denik != null) {
            $this->notifikaceRepository->oznacitNotifikaceJakoPrectene($this->denik->getId(), TypNotifikace::FINANCNI_PLAN_KOMPLET_NAKUP, false);
            $this->notifikaceRepository->oznacitNotifikaceJakoPrectene($this->denik->getId(), TypNotifikace::FINANCNI_PLAN_POLOZKA_NAKUP, false);
            $this->notifikaceRepository->oznacitNotifikaceJakoPrectene($this->denik->getId(), TypNotifikace::FINANCNI_PLAN_ZRUSENA_POLOZKA, false);
        }
    }

    public function createComponentFinancePrehledGrid(): Multiplier
    {
        return new Multiplier(function ($idOddeleni) {

            $grid = new DataGrid();
            $grid->setDataSource($this->getDatasource($idOddeleni));
            $grid->setTranslator($this->translator);
            $grid->setPagination(false);

            if (in_array($idOddeleni, $this->pravaOddily["Schval"]) || in_array($idOddeleni, $this->pravaOddily["Nakup"])) {
                $grid->addColumnText("idax", $this->translator->translate('finance.idax'));
                    // ->setRenderer( fn($item) => $item["idax"] ? $item["idax"]->getIdax() : "");
            }
            
            $grid->addColumnText("katalogovyNazevVydaje", $this->translator->translate('finance.katalogovyNazevVydaje'));

            $grid->addColumnText("stredisko", $this->translator->translate('finance.stredisko'))
                ->setRenderer(function ($item) {
                    return is_null($item["stredisko"]) ? "" : $item["stredisko"]["nazev"];
                })
                ->setEditableInputTypeSelect($this->strediskaRepository->getPairArray("getId", "getNazev"));

            $grid->addColumnText("typPolozky", $this->translator->translate('finance.typPolozky'))
                ->setRenderer(function ($item) {
                    return $this->financeTypPolozkyRepository->find($item["typPolozky"]["id"])->getNazev();
                })->setEditableInputTypeSelect($this->financeTypPolozkyRepository->toArray())
                ->addAttributes(["title" => $this->translator->translate('finance.typPolozkyTitle')]);

            $grid->addColumnText("stav", $this->translator->translate('finance.stav'))
                ->setRenderer(function ($item) {
                    return  $item["stav"] == 1 
                    ? $this->translator->translate('finance.EnumStav.schvaleno') 
                    : $this->translator->translate('finance.EnumStav.neschvaleno');
                })
                ->setTemplate(__DIR__ . "/../components/ColumnStavSchval.latte");

            $grid->addColumnText("mnozstvi", $this->translator->translate('finance.mnozstvi'))
                ->addAttributes(["title" => $this->translator->translate('finance.mnozstviTitle')]);

            $grid->addColumnText("jednotka", $this->translator->translate('finance.jednotka'))
                ->setRenderer(function ($item) {
                    return !is_numeric($item["jednotka"]) ? "" : EnumJednotky::toTranslatedArrray($this->translator)[$item["jednotka"]];
                });
            
            if (in_array($idOddeleni, $this->pravaOddily["Edit"])) {
                $grid->addColumnText("vyzvednutoKusu", $this->translator->translate('finance.vyzvednutoKusu'))
                    ->setRenderer(fn ($item) => $item["vyzvednutoKusu"] ?? null);
            }

            $grid->addColumnNumber("cenaKonecna", $this->translator->translate('finance.cenaKonecna'));

            $grid->addColumnText("dodavatel", $this->translator->translate('finance.dodavatel'));

            $grid->addColumnDateTime("terminDodani", $this->translator->translate('finance.terminDodani'))
                ->setAlign("center");

            $grid->addColumnText("objednavka", $this->translator->translate('finance.objednavka'))
                ->setAlign("center");
            
            $grid->addColumnText("nazev", $this->translator->translate('finance.nazevOdkaz'))
                ->setRenderer(function ($item) {
                    if (strpos($item["nazev"], "www") !== false || strpos($item["nazev"], "http") !== false) {
                        return Html::el("a")
                            ->addText($item["nazev"])
                            ->addAttributes([
                                "href" => $item["nazev"],
                                "target" => "_blank"
                            ]);
                    } else if (strpos($item["nazev"], "http") !== false) {
                        return Html::el("a")
                            ->addText($item["nazev"])
                            ->addAttributes([
                                "href" => "http://" . $item["nazev"],
                                "target" => "_blank"
                            ]);
                    } else {
                        return $item["nazev"];
                    }
                });

            $grid->addColumnText("poznamka", $this->translator->translate('finance.poznamka'));

            $grid->addColumnText("pridal", $this->translator->translate('finance.pridal'))->setRenderer(function ($item) {
                    return  $item["pridal"]["jmeno"] . " " . $item["pridal"]["prijmeni"];
                });

            $grid->addColumnText("nakupci", $this->translator->translate('finance.nakupci'))
                ->setRenderer(function ($item) {
                    return $item["nakupci"] ? $item["nakupci"]["jmeno"] . " " . $item["nakupci"]["prijmeni"] : ""; 
                });

            $grid->addColumnText("zaznamPriloha", $this->translator->translate('finance.priloha'));

            if ($this->denik->getZamknuty() == 0) {

                //práva EDIT
                if ($this->pravaOddily["Edit"] != null && in_array($idOddeleni, $this->pravaOddily["Edit"])) {

                    $grid->addInlineEdit()->onControlAdd[] = function (Container $container): void {
                        $container->addText("katalogovyNazevVydaje", "");
                        $nazvy = $this->strediskaRepository->getPairArray("getId", "getNazev", fn($stredisko) => $stredisko->getWerk()->getId() == $this->user->getIdentity()->werk);
                        $container->addSelect("stredisko","", $nazvy);
                        $container->addSelect("typPolozky", "", $this->financeTypPolozkyRepository->toArray());
                        $container->addText("nazev", "");
                        $container->addText("dodavatel", "");
                        $container->addText("mnozstvi", "");
                        $container->addSelect("jednotka", "", EnumJednotky::toTranslatedArrray($this->translator));
                        $container->addInteger("cenaKonecna", "");
                        $container->addText('poznamka','');

                        $container->getForm()->onSubmit[] = function ($form) {
                            if ($form->isSubmitted()->getName() == "cancel") {
                                $this->redirect("this");
                            }
                        };
                    };

                    $grid->getInlineEdit()->setClass("btn ajax");

                    $grid->allowRowsInlineEdit(function ($item) {
                        return $item["stav"] == 0;
                    });

                    $grid->addActionCallback('file', '')
                        ->setRenderer(function ($item) {
                            return '<label class="btn mt-2" for="' . $item["id"] . '"><i class="fa fa-upload"></i></label>
                                    <input type="file" name="fileUpload" id="' . $item["id"] . '" />';
                        });

                    $grid->getInlineEdit()->onSetDefaults[] = function (Container $container, $item) {
                        $container->setDefaults([
                            "katalogovyNazevVydaje" => $item["katalogovyNazevVydaje"],
                            "stredisko" => $item["stredisko"]["id"],
                            "typPolozky" => $item["typPolozky"]["id"],
                            "nazev" => $item["nazev"],
                            "dodavatel" => $item["dodavatel"],
                            "mnozstvi" => $item["mnozstvi"],
                            "jednotka" => $item["jednotka"],
                            "cenaKonecna" => $item["cenaKonecna"],
                            "poznamka" => $item["poznamka"]
                        ]);
                    };

                    $grid->getInlineEdit()->onSubmit[] = function ($id, ArrayHash $values) use ($grid, $idOddeleni) {
                        $zaznam = $this->financeZaznamRepository->find($id);
                        if ($zaznam->getStav() == 0) {
                            $zaznam->setKatalogovyNazevVydaje($values["katalogovyNazevVydaje"]);
                            $zaznam->setStredisko($this->strediskaRepository->find($values["stredisko"]));
                            $zaznam->setTypPolozky($this->financeTypPolozkyRepository->find($values["typPolozky"]));
                            $zaznam->setNazev($values["nazev"]);
                            $zaznam->setDodavatel($values["dodavatel"]);
                            if (strpos($values["mnozstvi"], "*") != false) {
                                $mnozstvi = $values["mnozstvi"];
                                $pos = strpos($mnozstvi, "*");
                                $mno = substr($mnozstvi, 0, $pos);
                                $cen = substr($mnozstvi, $pos + 1);
                                $cena = intval($mno) * intval($cen);
                                $values["mnozstvi"] = intval($mno);
                                $values["cenaKonecna"] = $cena;
                            }
                            $zaznam->setMnozstvi($values["mnozstvi"]);
                            $zaznam->setJednotka($values["jednotka"]);
                            $zaznam->setCenaKonecna($values["cenaKonecna"]);
                            $zaznam->setPoznamka($values["poznamka"]);
                            // $zaznam->setIdax($this->financeKatalogRepository->findOneBy(["nazev" => $values->katalogovyNazevVydaje]));
                            $this->financeZaznamRepository->editZaznam($zaznam);
                        } else {
                            $this->flashMessage($this->translator->translate('finance.errorUzSchvaleno'));
                        }
                        $grid->setDataSource($this->getDatasource($idOddeleni));
                        $this->redirect("FinancePrehled:default", ["datum" => $this->datum]);
                    };


                    $grid->addInlineAdd()->onControlAdd[] = function (Container $container) {
                        $container->addText('katalogovyNazevVydaje', '');
                        $nazvy = $this->strediskaRepository->getPairArray("getId", "getNazev", fn($stredisko) => $stredisko->getWerk()->getId() == $this->user->getIdentity()->werk);
                        $container->addSelect("stredisko","", $nazvy);
                        $container->addSelect('typPolozky', '', $this->financeTypPolozkyRepository->toArray());
                        $container->addText("nazev", '');
                        $container->addText("dodavatel", '');
                        $container->addText("mnozstvi", '');
                        $container->addSelect("jednotka", "", EnumJednotky::toTranslatedArrray($this->translator));
                        $container->addInteger("cenaKonecna", '');
                        $container->addText('poznamka','');
                    };

                    $grid->getInlineAdd()->setPositionTop();

                    $grid->getInlineAdd()->onSubmit[] = function (ArrayHash $values) use ($grid, $idOddeleni): void {
                        // $katalogovyZaznam = $this->financeKatalogRepository->findOneBy(["nazev" => $values->katalogovyNazevVydaje]);
                        // $values->idax = $katalogovyZaznam;
                        //získání dodatečných informací jako je uživatelovo Id a první den dalšího měsíce
                        $values->uzivatel = $this->user->getId();
                        // převedení unix timu na DateTime
                        $prvniDenDalsihoMesice = gmdate("Y-m-d", strtotime('first day of next month'));
                        $values->datum = new DateTime($prvniDenDalsihoMesice);
                        $values->denik = $this->denik;
                        if (strpos($values->mnozstvi, "*") != false) {
                            $pos = strpos($values->mnozstvi, "*");
                            $mno = substr($values->mnozstvi, 0, $pos);
                            $cen = substr($values->mnozstvi, $pos + 1);
                            $cena = intval($mno) * intval($cen);
                            $values->mnozstvi = intval($mno);
                            $values->cenaKonecna = $cena;
                        }
                        if (!$values->mnozstvi){
                            $values->mnozstvi = 1;
                        }
                        // if ($values->idax && !$values->cenaKonecna) {
                        //     $values->cenaKonecna = intval(round($katalogovyZaznam->getCena() * $values->mnozstvi));
                        // }
                        $this->financeZaznamRepository->ulozitZaznam($idOddeleni, $values);
                        $grid->setDatasource($this->getDatasource($idOddeleni));
                        $this->redrawControl();
                        $this->redirect("FinancePrehled:default", ["datum" => $this->datum]);
                    };
                }




                //práva SCHVAL or EDIT
                if (($this->pravaOddily["Schval"] != null && in_array($idOddeleni, $this->pravaOddily["Schval"])) || ($this->pravaOddily["Edit"] != null && in_array($idOddeleni, $this->pravaOddily["Edit"]))) {
                    $grid->addActionCallback("delete", "")
                        ->setRenderCondition(function ($item) {
                            return ($item["stav"] == 0);
                        })
                        ->setClass("btn btn-sm btn-danger ajax")
                        ->setIcon("times")
                        ->onClick[] = function ($id) use ($grid, $idOddeleni) {

                            $zaznam = $this->financeZaznamRepository->find($id);
                            $this->financeZaznamRepository->smazatZaznam($id);
                            //odeslat notifikaci uživateli co zadal

                            $this->notifikaceRepository->vytvoritNotifikaci(
                                TypNotifikace::FINANCNI_PLAN_ZRUSENA_POLOZKA,
                                $this->denik->getId(),
                                true,
                                [
                                    "odkazDatum" => $this->denik->getDatumZacatkuDeniku()->format("Y-m-d"),
                                    "datum" => $this->denik->getDatumZacatkuDeniku()->format("m.Y"),
                                    "schvalene" => [$zaznam] //použití šablony jako pro schválené #lazyAF
                                ],
                                [],
                                $this->translator->translate('finance.emailOdstraneniPolozky') . " " . $this->denik->getDatumZacatkuDeniku()->format("m.Y")
                            );
                            $grid->setDatasource($this->getDatasource($idOddeleni));
                            $this->redrawControl();
                    };
                }


                //práva SCHVAL
                if (($this->pravaOddily["Schval"] != null && in_array($idOddeleni, $this->pravaOddily["Schval"]))) {
                    $grid->addActionCallback("odlozit", "")
                        ->setRenderCondition(function ($item) {
                            return ($item["stav"] == 0);
                        })
                        ->setClass("btn btn-sm btn-warning ajax")
                        ->setIcon("arrow-right")
                        ->setConfirm($this->translator->translate('finance.confirmOdlozeniPolozky'))
                        ->onClick[] = function ($id) use ($grid, $idOddeleni) {
                        $datumOdlozeni = date_modify(
                            $this->financeDenikRepository->find($this->denik->getId())->getDatumZacatkuDeniku(),
                            "+1 month"
                        );
                        $this->financeZaznamRepository->odlozitZaznam($id, $datumOdlozeni);
                        $grid->setDatasource($this->getDatasource($idOddeleni));
                        $this->redrawControl();
                        $this->redirect("FinancePrehled:default", ["datum" => $this->datum]);
                    };
                }
            } // is zamknuty denik

            $grid->addGroupAction($this->translator->translate('finance.email'))->onSelect[] = function ($ids) {
                $mailData = $this->financeZaznamRepository->findBy(["id" => $ids]);
                $body = "";
                foreach ($mailData as $zaznam) {
                    $body .= $zaznam->getKatalogovyNazevVydaje()
                    . "%20"
                    . $zaznam->getMnozstvi()
                    . EnumJednotky::toTranslatedArrray($this->translator)[$zaznam->getJednotka()]
                    . "   "
                    . urlencode($zaznam->getNazev())
                    . "%0D%0A";
                }
                $this->redirectUrl("mailto:?subject=Měsíční plán&body=" . $body);
            };

            if ($this->pravaOddily["Edit"] != null && in_array($idOddeleni, $this->pravaOddily["Edit"])) {
                $grid->addGroupAction($this->translator->translate('finance.kopirovat'))->onSelect[] = [$this, "doKopirovatDoDalsihoDeniku"];

                $grid->addGroupAction($this->translator->translate('finance.smazat'))
                    ->onSelect[] = function ($ids) {
                        // TODO konzultace - modal je dost složitý, ale úplně bez confirmu je to také ne úplně v pohodě
                        foreach($ids as $id) {
                            $this->financeZaznamRepository->deleteById($id);
                        }
                        $this->redirect("this");
                    };
                
                $grid->addActionCallback("smazatPrilohu", "")
                    ->setClass("fa fa-trash btn")
                    ->setRenderCondition(function ($item) {
                        return $item["zaznamPriloha"] != null;
                    })
                    ->onClick[] = function ($id) use ($grid, $idOddeleni) {
                    try {
                        $path = __DIR__ . '/../../../www/storage/financePrilohy/';
                        $path = $path . $this->financeZaznamRepository->find($id)->getZaznamPriloha();
                        if (is_file($path) && $this->financeZaznamRepository->find($id)->getZaznamPriloha() != null) {
                            $this->financeZaznamRepository->find($id)->setZaznamPriloha("");
                            $this->financeZaznamRepository->em->flush();
                            FileSystem::delete($path);
                        }
                    } catch (Exception $e) {
                    }
                    $grid->setDatasource($this->getDatasource($idOddeleni));
                    $this->redrawControl();
                };
            }

            if (($this->pravaOddily["Schval"] != null && in_array($idOddeleni, $this->pravaOddily["Schval"]))) {
                $grid->addGroupAction($this->translator->translate('finance.schvalitVybrane'))->onSelect[] = [$this, "doSchvalitVybrane"];
            }

             /**
             * group action pro vyzvedávání balíčků a následný export výdejky do csv
             */
            $grid->addGroupAction($this->translator->translate('finance.vydejka'))->onSelect[] = function ($ids) {
                $this->proVydej = $this->financeZaznamRepository->findBy(["id" => $ids, "stav" => 1]);
                foreach ($this->proVydej as $key => $zaznam) {
                    if ($zaznam->getVyzvednutoKusu() == $zaznam->getMnozstvi()) {
                        unset($this->proVydej[$key]);
                    }
                }
                if (!$this->proVydej) {
                    $this->flashMessage($this->translator->translate('finance.nelzeVyzvednout'), "alert alert-danger");
                    $this->redrawControl();
                    return;
                }
                $this->redirect("FinancePrehledVydejka:", ["ids" => $ids, "datum" => $this->denik->getDatumZacatkuDeniku()->format("Y-m-d")]);
            };

            $grid->addActionCallback("stahnoutPrilohu", "")
                ->setClass("downPrldr fa fa-download btn")
                ->setRenderCondition(function ($item) {
                    if (is_file(__DIR__ . '/../../../www/storage/financePrilohy/' . $this->financeZaznamRepository->find($item["id"])->getZaznamPriloha())) {
                        return true;
                    } else {
                        return false;
                    }
                })
                ->onClick[] = function ($id) {
                if (is_file(__DIR__ . '/../../../www/storage/financePrilohy/' . $this->financeZaznamRepository->find($id)->getZaznamPriloha())) {
                    $response = new FileResponse(__DIR__ . '/../../../www/storage/financePrilohy/' . $this->financeZaznamRepository->find($id)->getZaznamPriloha());
                    $this->sendResponse($response);
                }
            };

            $grid->getColumn('objednavka')
                ->setRenderer(function ($item) {
                    $el = Html::el("span");

                    $item["objednavka"] = str_replace("\n", "", $item["objednavka"]);

                    if (is_numeric($item["objednavka"])) {
                        $el = Html::el("span")->setAttribute("class", "rohy-kul zelena pdTpBt");
                    } else if (!empty($item["objednavka"])) {
                        $el = Html::el("span")->setAttribute("class", "rohy-kul zluta pdTpBt");
                    } else {
                        // $el = Html::el("span")->setAttribute("class", "rohy-kul");
                        return $item["objednavka"];
                    }
                    // $obsah = !empty($item["objednavka"]) ? $item["objednavka"] : "asdsa";
                    return $el->setText($item["objednavka"]);
                });

            //práva NÁKUP
            if ($this->pravaOddily["Nakup"] != null && in_array($idOddeleni, $this->pravaOddily["Nakup"])) {
                $grid->getColumn('objednavka')
                    ->setEditableValueCallback(function ($item): string {
                        return $item["objednavka"] ?? "";
                    })
                    ->setEditableCallback(function ($id, $val) use ($grid, $idOddeleni) {

                        $zaz = $this->financeZaznamRepository->find($id);
                        $val = str_replace("\n", "", $val);
                        $zaz->setObjednavka($val);
                        $zaz->setNakupci($this->uzivateleRepository->find($this->user->getId()));
                        $this->financeZaznamRepository->em->flush();

                        $grid->setDatasource($this->getDatasource($idOddeleni));
                        $this->redrawControl();
                    })
                    ->setEditableOnConditionCallback(function ($item) {
                        // return $item["stav"] == 1;
                        return true;
                    });

                $grid->getColumn("cenaKonecna")
                    ->setEditableValueCallback(function ($item) {
                        return $item["cenaKonecna"] ?? null;
                    })
                    ->setEditableCallback(function ($id, $val) use ($grid, $idOddeleni) {
                        $zaznam = $this->financeZaznamRepository->find($id);
                        $val = str_replace(["\n", " "], "", $val);
                        if (is_numeric($val)) {
                            $zaznam->setCenaKonecna(intval($val));
                            $zaznam->setNakupci($this->uzivateleRepository->find($this->user->getId()));
                            // nastavení nové ceny v katalogu
                            // if ($zaznam->getIdax()) {
                                // vydělit konečnou cenu počtem kusů
                                // $zaznam->getIdax()->setCena(intdiv(intval($val), $zaznam->getMnozstvi()));
                            // }
                            $this->financeZaznamRepository->em->flush();
                            // $this->financeKatalogRepository->em->flush();
                        } else {
                            $this->flashMessage($this->translator->translate('finance.musiBytCislo'), "alert alert-danger");
                        }
                        $this->redrawControl();
                    });

                $grid->getColumn("terminDodani")
                    ->setEditableCallback(function ($id, $val)  use ($grid, $idOddeleni) {
                        $zaz = $this->financeZaznamRepository->find($id);
                        $val = str_replace("\n", "", $val);
                        try {
                            $datum = new DateTime($val);
                            $zaz->setTerminDodani($datum);
                            $zaz->setNakupci($this->uzivateleRepository->find($this->user->getId()));
                        } catch (\Throwable $th) {
                        }
                        $this->financeZaznamRepository->em->flush();
                        $grid->setDatasource($this->getDatasource($idOddeleni));
                        $this->redrawControl();
                    })
                    ->setEditableOnConditionCallback(function ($item) {
                        return $item["stav"] == 1;
                    });
                
                // $grid->addActionCallback("pridatDoKatalogu", "")
                //     ->setRenderCondition(function ($item) {
                //         return (!$item["idax"]->getIdax());
                //     })
                //     ->setClass("btn btn-sm btn-info ajax")
                //     ->setIcon("plus")
                //     ->onClick[] = function ($id) {
                //         $this->pridatDoKatalogu = $this->financeZaznamRepository->find(intval($id));

                //         // otevření modalu pro přidání do katalogu
                //         $this->redrawControl("pridatDoKatalogu");
                //     };
            }

            $grid->setColumnsSummary(['cenaKonecna'])
                ->setRenderer(function ($sum): string {
                    $sum = number_format($sum, 0, ".", "\xc2\xa0");
                    return $this->translator->translate('finance.celkem') . ": " . $sum . " " . $this->translator->translate('finance.KC');
                });

            $grid->addExportCsv('Csv export', $this->translator->translate('finance.exportCSV') . ' ' . $this->financeOddeleniRepository->find($idOddeleni)->getNazev() . " " . date_format($this->denik->getDatumZacatkuDeniku(), 'Y-m') . '.csv', 'windows-1250')
                ->setTitle($this->translator->translate('finance.exportCSV') . ' ' . $this->financeOddeleniRepository->find($idOddeleni)->getNazev())
                ->setClass("btn btn-xs btn-default btn-secondary clsPrldr")
                ->setColumns([
                    $grid->getColumn("katalogovyNazevVydaje"),
                    $grid->getColumn("nazev"),
                    $grid->getColumn("dodavatel"),
                    $grid->getColumn("mnozstvi"),
                    $grid->getColumn("typPolozky"),
                    $grid->getColumn("cenaKonecna"),

                ]);

            $grid->setRememberState(false);

            return $grid;
        });
    }

    public function handleExportCSV($meho)
    {

        $fileName = 'financniPlan_' . date_format($this->denik->getDatumZacatkuDeniku(), "m_Y") . '.csv';

        $userPravaArr = ["Edit" => array(), "Schval" => array(), "Nakup" => array(), "All" => array()];
        if ($meho == 1) {
            $userPravaArr = $this->financeOddilPravaRepository->findProUzivatele($this->user, FinanceOddilyPrava::MESICNI_PLAN);
        } else {
            $userPravaArr["All"] = $this->financeOddeleniRepository->getPairArray("getId", "getId");
        }
        $list = $this->financeZaznamRepository->getFinanceCSV($this->stav, $this->denik, $this->denik->getDatumZacatkuDeniku(), $userPravaArr["All"]);
        $fp = fopen($fileName, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            "katalogovyNazevVydaje" => $this->translator->translate('finance.katalogovyNazevVydajeProCsv'),
            "nazev" => $this->translator->translate('finance.nazevOdkaz'),
            "dodavatel" => $this->translator->translate('finance.dodavatel'),
            "pridal" => $this->translator->translate('finance.pridal'),
            "mnozstvi" => $this->translator->translate('finance.mnozstvi'),
            "typPolozky" => $this->translator->translate('finance.typPolozky'),
            "cenaKonecna" => $this->translator->translate('finance.cenaKonecna'),
            "TypFinance" => $this->translator->translate('finance.oddeleni'),

        ], ";");

        foreach ($list as $fields) {
            fputcsv($fp, [
                "katalogovyNazevVydaje" => $fields->getKatalogovyNazevVydaje(),
                "nazev" => $fields->getNazev(),
                "dodavatel" => $fields->getDodavatel(),
                "pridal" => $fields->getPridal()->getCeleJmeno(),
                "mnozstvi" => $fields->getMnozstvi(),
                "typPolozky" => $fields->getTypPolozky()->getNazev(),
                "cenaKonecna" => $fields->getCenaKonecna(),
                "TypFinance" => $fields->getFinanceOddeleni()->getNazev()
            ], ";");
        }
        fclose($fp);
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header("Content-Type: application/csv; charset=utf-8");
        header('Content-Type: application/force-download');
        header("Content-Transfer-Encoding: binary");
        ob_clean();
        readfile($fileName);
        unlink($fileName);
        exit();
    }
    public function createComponentFormSelect(): Form
    {
        $form = new Form();
        $form->setRenderer(new BootstrapV4Renderer);

        $form->addSelect("stav", $this->translator->translate('finance.stav'), [
                "" => $this->translator->translate('finance.vse'),
                1 => $this->translator->translate('finance.EnumStav.schvaleno'),
                0 => $this->translator->translate('finance.EnumStav.neschvaleno')
            ])
            ->setAttribute("class", "form-control")
            ->setDefaultValue($this->stav == null ? "" : $this->stav);
        $form->addText("mesic", $this->translator->translate('finance.mesic'))
            ->setAttribute("class", " form-control mb-2 mr-sm-2")
            ->setAttribute("id", "datepicker")
            ->setDefaultValue(date_format((new DateTime($this->date)), "m.Y"))
            ->setRequired()
            ->setAttribute("readonly");
        $form->addSubmit('find', $this->translator->translate('finance.hledej'))->setAttribute("class", "btn btn-primary mb-2 mr-sm-2");

        $form->onSuccess[] = [$this, "redirectNaDenik"];

        return $form;
    }

    public function redirectNaDenik(Form $form, $values) {
        $firstDayOfMonth = new DateTime("01." . $values->mesic);
        $denik = $this->financeDenikRepository->findOneBy(["datumZacatkuDeniku" => $firstDayOfMonth, "werk" => $this->user->getIdentity()->werk]);
        $this->redirect("FinancePrehled:default", [
            "stav" => $values->stav,
            "denik" => $denik,
            "datum" => date_format($firstDayOfMonth, "Y-m-d"),
        ]);
    }


    public function getDatasource($typ)
    {
        $dataSource = $this->financeZaznamRepository->getFinanceByTypArr($typ, $this->stav, $this->denik->getId());
        return $dataSource;
    }

    public function handleZmenitStavDeniku($zamknout)
    {

        $lock = $this->denik->getZamknuty() == 1 ? 0 : 1;
        $this->financeDenikRepository->zamknoutDenik($this->denik->getId(), $lock);
        $this->financeZaznamRepository->presunNeschvalenychDoDalsihoDeniku($this->denik);
        $this->redrawControl();
        $this->redirect("this");
    }

    public function handleOdeslatNotifikaci()
    {
        if ($this->denik->getZamknuty() == 1) {
            $this->notifikaceRepository->vytvoritNotifikaci(
                TypNotifikace::FINANCNI_PLAN_KOMPLET_NAKUP,
                $this->denik->getId(),
                true,
                [
                    "odkazDatum" => $this->denik->getDatumZacatkuDeniku()->format("Y-m-d"),
                    "datum" => $this->denik->getDatumZacatkuDeniku()->format("m.Y")
                ],
                []
            );
        }
    }

    public function handleVytvorDenik($datum)
    {
        $datum = null;
        $werk = $this->werkRepository->find($this->user->getIdentity()->werk);

        foreach ($this->getHttpRequest()->getQuery() as $key => $value) {

            if ($value == "datum") {

                $datum = explode("_", $key);
                $datum = $datum[0] . "/01" .  "/" . $datum[1];
                $datum = new DateTimeImmutable($datum);
                $datum->modify('first day of this month');
                if(!$this->financeDenikRepository->findDenik($datum, $datum->modify("+1 day"), $werk)){
                    $this->financeDenikRepository->createDenik($datum, $werk);
                }
            }
        }
        $datumMax = new DateTime(date_format($datum, 'Y-m-d'));
        $datumMax->modify("+1 month");
        $datumMax->modify('first day of this month');

        $denik = $this->financeDenikRepository->findDenik($datum, $datumMax, $werk)[0]["ID"];
        $this->redrawControl();
        $this->redirect("FinancePrehled:default", array("stav" => $this->stav, "denik" => $denik, "datum" => date_format($datum, "Y-m-d")));
    }
    public function handleUploadFile($file, $zaznamId)
    {
        $nameFile = 0;
        foreach ($this->getHttpRequest()->getQuery() as $key => $value) {
            if ($value == "zaznamId") $nameFile = $key;
        }


        $values = $this->getHttpRequest()->getFiles()["file"];
        $path = __DIR__ . '/../../../www/storage/financePrilohy/' . $values->getName();
        $values->move($path);


        $this->financeZaznamRepository->setPriloha($nameFile, $values->getName());

        $this->redirect("this");
    }

    public function handleVyhledaniRedir()
    {
        $this->redirect(":Controlling:FinancePrehledVyhledani:");
    }

    public function doSchvalitVybrane($ids)
    {
        $schvalene = [];
        foreach ($ids as $id) {
            $zaz = $this->financeZaznamRepository->find($id);
            if ($zaz->getStav() == 0) {
                $schvalene[] = $zaz;
                $zaz->setStav(1);
            }
            $this->financeZaznamRepository->em->persist($zaz);
        }
        $this->financeZaznamRepository->em->flush();
        $mailData = ["schvalene" => $schvalene, "odkazDatum" => $this->denik->getDatumZacatkuDeniku()->format("Y-m-d"), "datum" => $this->denik->getDatumZacatkuDeniku()->format("m.Y")];

        $this->notifikaceRepository->vytvoritNotifikaci(TypNotifikace::FINANCNI_PLAN_POLOZKA_NAKUP, $this->denik->getId(), true, $mailData, []);
        
        $this->redirect("this");
    }

    public function doKopirovatDoDalsihoDeniku($ids)
    {
        foreach ($ids as $id) {
            $zaz = $this->financeZaznamRepository->find($id);
            $this->financeZaznamRepository->copyProNovyDenik($zaz, $zaz->getFinanceOddeleni(), true);
        }

        $this->redirect("this");
    }

    public function handleRedirectNaDenik($datum)
    {
        $this->redirect("FinancePrehled:default", ["datum" => $datum]);
    }

    // public function createComponentAddToKatalogForm(): Form
    // {
    //     $form = new Form;
    //     $form->setRenderer(new BootstrapV4Renderer);
    //     $form->addText("idax", $this->translator->translate('finance.idax'))
    //         ->setAttribute("class", "form-control mb-2")
    //         ->setRequired();
    //     $form->addText("nazev", $this->translator->translate('finance.katalogovyNazevVydaje'))
    //         ->setAttribute("class", "form-control mb-2")
    //         ->setDefaultValue($this->pridatDoKatalogu ? $this->pridatDoKatalogu->getKatalogovyNazevVydaje() : "")
    //         ->setRequired();
    //     $form->addText("cena", $this->translator->translate('finance.jednotkovaCena'))
    //         ->setAttribute("class", "form-control mb-2")
    //         ->setDefaultValue(
    //             $this->pridatDoKatalogu ?
    //                 $this->pridatDoKatalogu->getCenaKonecna() / $this->pridatDoKatalogu->getMnozstvi()
    //                 : 0
    //         )
    //         ->setRequired();
    //     $form->addHidden("id", $this->pridatDoKatalogu ? $this->pridatDoKatalogu->getId() : "");
    //     $form->addSubmit("addToKatalog", $this->translator->translate('finance.pridatDoKatalogu'))
    //         ->setAttribute("class", "btn btn-primary mb-2 mr-sm-2");

    //     $this->redrawControl();


    //     $form->onSuccess[] = function ($form, $values) {
    //         // vytvoření nebo úprava záznamu v katalogu
    //         $zaznam = $this->financeKatalogRepository->find($values->idax);
    //         if (!$zaznam) {
    //             $zaznam = new FinanceKatalog;
    //         }
    //         $zaznam->setIdax($values->idax);
    //         $zaznam->setNazev($values->nazev);
    //         if (strpos($values->cena, ",")) {
    //             $values->cena = str_replace(",", ".", $values->cena);
    //         }
    //         $zaznam->setCena(floatval($values->cena));
    //         $this->financeKatalogRepository->save($zaznam);

    //         // upravit i záznam ve finančním deníku
    //         $zaznamFinDen = $this->financeZaznamRepository->find(intval($values->id));
    //         $zaznamFinDen->setKatalogovyNazevVydaje($values->nazev);
    //         $zaznamFinDen->setIdax($zaznam);
    //         $zaznamFinDen->setCenaKonecna(intval(round(floatval($values->cena) * floatval($zaznamFinDen->getMnozstvi()))));
    //         $this->financeZaznamRepository->save($zaznamFinDen);
    //         $this->flashMessage($this->translator->translate('messages.novyZaznamObecne'), "alert alert-success");

    //         $this->pridatDoKatalogu = null;
    //     };

    //     return $form;
    // }
}
