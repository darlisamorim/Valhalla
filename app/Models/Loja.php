<?php

namespace App\Models;

use App\Nucleo\Loja\NenhumaLojaConfigurada;
use App\Nucleo\Loja\ResolvedorDeLoja;
use App\Nucleo\Modulos\Modulo;
use Database\Factories\LojaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loja (tenant). Fonte da verdade de `loja_id` (CLAUDE.md 5.1).
 *
 * Também é a **fachada de acesso** à loja atual: todo o sistema chama `Loja::atual()` e
 * nunca o pacote de multi-tenancy (regra de ouro 3). Quem responde de fato é o
 * ResolvedorDeLoja registrado no container — `SingleStoreResolver` no modo loja única,
 * `TenancyResolver` (pacote privado) na edição SaaS.
 *
 * @property int $id
 * @property string $nome
 * @property string $slug
 * @property string|null $dominio
 * @property bool $ativa
 * @property array|null $configuracoes
 */
class Loja extends Model
{
    /** @use HasFactory<LojaFactory> */
    use HasFactory;

    protected $table = 'lojas';

    protected $fillable = [
        'nome',
        'slug',
        'dominio',
        'ativa',
        'configuracoes',
    ];

    protected function casts(): array
    {
        return [
            'ativa' => 'boolean',
            'configuracoes' => 'array',
        ];
    }

    // ------------------------------------------------------------------ loja atual

    /**
     * A loja atual. Estoura se não houver nenhuma — ver NenhumaLojaConfigurada.
     */
    public static function atual(): self
    {
        return static::atualOuNula() ?? throw NenhumaLojaConfigurada::paraModoSingle();
    }

    /**
     * A loja atual, ou null. Use quando a ausência de loja é um estado legítimo
     * (instalador, painel mestre fora do contexto de uma loja).
     */
    public static function atualOuNula(): ?self
    {
        return app(ResolvedorDeLoja::class)->atual();
    }

    /**
     * Passa a tratar esta loja como a atual pelo resto da requisição.
     */
    public function tornarAtual(): void
    {
        app(ResolvedorDeLoja::class)->definir($this);
    }

    // -------------------------------------------------------------------- módulos

    /**
     * @return HasMany<LojaModulo, $this>
     */
    public function modulos(): HasMany
    {
        return $this->hasMany(LojaModulo::class);
    }

    /**
     * Este módulo está ligado nesta loja?
     *
     * Módulo essencial responde sempre true — não é desligável (não compõe plano).
     */
    public function temModulo(Modulo $modulo): bool
    {
        if ($modulo->essencial()) {
            return true;
        }

        return $this->modulos()
            ->where('modulo', $modulo->value)
            ->where('ativo', true)
            ->exists();
    }
}
