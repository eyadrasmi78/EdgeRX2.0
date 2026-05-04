<?php

namespace App\Services;

use App\Models\PricingAgreement;
use App\Models\PricingAgreementItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Phase D2 — pricing source resolver.
 *
 * Given a (customer, supplier, product, qty), returns:
 *   - the unit price to charge
 *   - which source it came from (CATALOG | CONTRACT)
 *   - the agreement_id + version + savings (if CONTRACT)
 *   - any warning (e.g. MOQ shortfall fallback)
 *
 * Decision rules (per locked decision: contract beats catalog/promo;
 * buying-group is independent and resolved separately):
 *   1. Find ACTIVE agreements where (customer_id matches or scope-applies-to)
 *      AND items.product_id = product AND now() ∈ [valid_from, valid_to].
 *   2. If qty ≥ MOQ → use contract price (or tier-break price).
 *   3. Else apply moq_fallback_mode:
 *      FALLBACK_CATALOG → use catalog (pricingSource=CATALOG, warning attached)
 *      BLOCK             → throw DomainException 'moq_below_threshold'
 *      SPLIT             → caller is responsible for splitting; resolver returns
 *                          a SPLIT recommendation in the result.
 *   4. If no agreement → catalog.
 */
final class PriceResolver
{
    public function resolve(string $customerId, string $supplierId, string $productId, int $quantity): array
    {
        $product = Product::find($productId);
        if (!$product) {
            throw new \DomainException('product_not_found');
        }
        $catalogPrice = (float) $product->price;

        $agreementItem = $this->findActiveAgreementItem($customerId, $supplierId, $productId, $quantity);

        if (!$agreementItem) {
            return $this->catalogResult($catalogPrice, $quantity);
        }

        $agreement = $agreementItem->agreement;
        $contractPrice = $agreementItem->priceForQuantity($quantity);
        $moq = (int) $agreementItem->min_order_quantity;

        // qty ≥ MOQ → contract wins
        if ($quantity >= $moq) {
            $savings = round(($catalogPrice - $contractPrice) * $quantity, 2);
            return [
                'unitPrice'              => $contractPrice,
                'pricingSource'          => 'CONTRACT',
                'pricingAgreementId'     => $agreement->id,
                'pricingAgreementNumber' => $agreement->agreement_number,
                'pricingAgreementVersion'=> (int) $agreement->version,
                'catalogUnitPrice'       => $catalogPrice,
                'contractedUnitPrice'    => $contractPrice,
                'savingsAmount'          => max(0, $savings),
                'minOrderQuantity'       => $moq,
                'warning'                => null,
                'splitRecommended'       => null,
            ];
        }

        // qty < MOQ → fall back per agreement.moq_fallback_mode
        $mode = $agreement->moq_fallback_mode;

        if ($mode === 'BLOCK') {
            throw new \DomainException("moq_below_threshold:{$moq}");
        }

        if ($mode === 'SPLIT') {
            // Recommend splitting: 0 units at contract price (since qty<MOQ no qualifying portion)
            // Note: a true SPLIT case applies when MOQ is per-LINE not per-ORDER. For per-line,
            // SPLIT is meaningless if qty<MOQ. SPLIT is more relevant when there's a tier-MOQ
            // (e.g. first 100 at contract, remainder at catalog) — modeled via tier_breaks.
            // For now: under MOQ → fallback to catalog with informational warning.
            return [
                'unitPrice'              => $catalogPrice,
                'pricingSource'          => 'CATALOG',
                'pricingAgreementId'     => null,
                'pricingAgreementNumber' => null,
                'pricingAgreementVersion'=> null,
                'catalogUnitPrice'       => $catalogPrice,
                'contractedUnitPrice'    => null,
                'savingsAmount'          => 0,
                'minOrderQuantity'       => $moq,
                'warning'                => "Below MOQ ({$moq}) — catalog price applied. Bump qty to qualify for contract price " . number_format($contractPrice, 2) . ".",
                'splitRecommended'       => $moq, // hint to UI: "to split, order at least N more"
            ];
        }

        // FALLBACK_CATALOG (default)
        return [
            'unitPrice'              => $catalogPrice,
            'pricingSource'          => 'CATALOG',
            'pricingAgreementId'     => null,
            'pricingAgreementNumber' => null,
            'pricingAgreementVersion'=> null,
            'catalogUnitPrice'       => $catalogPrice,
            'contractedUnitPrice'    => null,
            'savingsAmount'          => 0,
            'minOrderQuantity'       => $moq,
            'warning'                => "Below MOQ ({$moq}) — catalog price applied. Bump qty to qualify for contract price " . number_format($contractPrice, 2) . ".",
            'splitRecommended'       => null,
        ];
    }

    /**
     * Find a pricing_agreement_item belonging to an ACTIVE agreement that
     * applies to (customer + product + supplier).
     *
     * BE-16 fix: when multiple agreements apply, return the CHEAPEST line
     * for the requested quantity (customer always gets the best deal they
     * legally have access to).
     *
     * BE-17 fix: single SQL join across pricing_agreements + items so we
     * don't loop and run one query per agreement candidate.
     */
    private function findActiveAgreementItem(string $customerId, string $supplierId, string $productId, int $quantity = 1): ?PricingAgreementItem
    {
        $today = now()->toDateString();

        // Single eager-loaded query: pull all candidate agreement+items in one round trip
        $candidates = PricingAgreement::with(['items' => function ($q) use ($productId) {
                $q->where('product_id', $productId);
            }])
            ->where('status', 'ACTIVE')
            ->where('supplier_id', $supplierId)
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_to',   '>=', $today)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->orWhere('scope', 'MASTER_AND_CHILDREN')
                  ->orWhere('scope', 'SPECIFIC_CHILDREN');
            })
            ->get();

        $applicable = $candidates->filter(fn(PricingAgreement $a) => $a->appliesTo($customerId));
        if ($applicable->isEmpty()) return null;

        // Collect every (agreement, item) pair where the item exists and pick
        // the one with the lowest priceForQuantity at the requested qty.
        $best = null; $bestPrice = null;
        foreach ($applicable as $a) {
            foreach ($a->items as $item) {
                if ($item->product_id !== $productId) continue;
                $p = $item->priceForQuantity($quantity);
                if ($bestPrice === null || $p < $bestPrice) {
                    $best = $item;
                    $best->setRelation('agreement', $a);
                    $bestPrice = $p;
                }
            }
        }
        return $best;
    }

    private function catalogResult(float $catalogPrice, int $qty): array
    {
        return [
            'unitPrice'              => $catalogPrice,
            'pricingSource'          => 'CATALOG',
            'pricingAgreementId'     => null,
            'pricingAgreementNumber' => null,
            'pricingAgreementVersion'=> null,
            'catalogUnitPrice'       => $catalogPrice,
            'contractedUnitPrice'    => null,
            'savingsAmount'          => 0,
            'minOrderQuantity'       => null,
            'warning'                => null,
            'splitRecommended'       => null,
        ];
    }

    /**
     * Compute period utilization for a given customer × agreement_item.
     * Returns ['used' => N, 'committed' => M, 'cap' => K, 'pctUsed' => 0..100].
     * Reads from `orders` rows tagged with this agreement_id.
     */
    public function periodUtilization(PricingAgreementItem $item): array
    {
        $agreement = $item->agreement;
        if (!$agreement) {
            return ['used' => 0, 'committed' => null, 'cap' => null, 'pctUsed' => 0];
        }

        $used = (int) \DB::table('orders')
            ->where('pricing_agreement_id', $agreement->id)
            ->where('product_id', $item->product_id)
            ->whereBetween('date', [$agreement->valid_from, $agreement->valid_to])
            ->sum('quantity');

        $cap = $item->max_period_quantity ? (int) $item->max_period_quantity : null;
        $committed = $item->committed_period_quantity ? (int) $item->committed_period_quantity : null;
        $pctUsed = $cap ? (int) round(min(100, ($used / max(1, $cap)) * 100)) : 0;

        return [
            'used'      => $used,
            'committed' => $committed,
            'cap'       => $cap,
            'pctUsed'   => $pctUsed,
        ];
    }
}
