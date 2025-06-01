<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "transaction")]
class Transaction
{
    #[MongoDB\Id]
    private $id;

    #[MongoDB\Field(type: "string")]
    private ?string $ville = null;

    #[MongoDB\Field(type: "string")]
    private ?string $code_postal = null;

    #[MongoDB\Field(type: "date")]
    private ?\DateTime $date = null;

    #[MongoDB\Field(type: "float")]
    private ?float $valeur_fonciere = null;

    #[MongoDB\Field(type: "int")]
    private ?int $surface_terrain = null;

    #[MongoDB\Field(type: "float")]
    private ?float $longitude = null;

    #[MongoDB\Field(type: "float")]
    private ?float $latitude = null;

    public function getId(): ?string
    {
        return (string) $this->id;
    }

    #[MongoDB\Field(type: "int")]
    private ?int $nombre_pieces_principales = null;

    #[MongoDB\Field(type: "float")]
    private ?float $surface_reelle_bati = null;



    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $ville): self { $this->ville = $ville; return $this; }

    public function getCodePostal(): ?string { return $this->code_postal; }
    public function setCodePostal(?string $code_postal): self { $this->code_postal = $code_postal; return $this; }

    public function getDate(): ?\DateTime { return $this->date; }
    public function setDate(?\DateTime $date): self { $this->date = $date; return $this; }

    public function getValeurFonciere(): ?float { return $this->valeur_fonciere; }
    public function setValeurFonciere(?float $valeur_fonciere): self { $this->valeur_fonciere = $valeur_fonciere; return $this; }

    public function getSurfaceTerrain(): ?int { return $this->surface_terrain; }
    public function setSurfaceTerrain(?int $surface_terrain): self { $this->surface_terrain = $surface_terrain; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }

    public function getNombrePiecesPrincipales(): ?int
    {
        return $this->nombre_pieces_principales;
    }

    public function setNombrePiecesPrincipales(?int $nombre): self
    {
        $this->nombre_pieces_principales = $nombre;
        return $this;
    }
    public function getSurfaceReelleBati(): ?float
    {
        return $this->surface_reelle_bati;
    }

    public function setSurfaceReelleBati(?float $surface): self
    {
        $this->surface_reelle_bati = $surface;
        return $this;
    }

}
