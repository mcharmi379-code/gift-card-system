<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardVoucher;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use ICTECHGiftCard\Core\Content\GiftCardAuditLog\GiftCardAuditLogDefinition;
use ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionDefinition;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;

class GiftCardVoucherDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'ictech_gift_card_voucher';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardVoucherEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GiftCardVoucherCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('gift_card_id', 'giftCardId', GiftCardDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('giftCard', 'gift_card_id', GiftCardDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new StringField('code', 'code'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('original_amount', 'originalAmount'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('remaining_balance', 'remainingBalance'))->addFlags(new Required(), new ApiAware()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new StringField('sender_name', 'senderName'))->addFlags(new ApiAware()),
            (new StringField('recipient_name', 'recipientName'))->addFlags(new ApiAware()),
            (new EmailField('recipient_email', 'recipientEmail'))->addFlags(new ApiAware()),
            (new LongTextField('personal_message', 'personalMessage'))->addFlags(new ApiAware()),

            (new DateField('scheduled_send_date', 'scheduledSendDate'))->addFlags(new ApiAware()),
            (new DateTimeField('sent_at', 'sentAt'))->addFlags(new ApiAware()),
            (new DateField('expires_at', 'expiresAt'))->addFlags(new ApiAware()),

            (new StringField('used_in_order_number', 'usedInOrderNumber'))->addFlags(new ApiAware()),
            (new StringField('delivery_method', 'deliveryMethod'))->addFlags(new ApiAware()),
            (new FkField('template_id', 'templateId', \ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateDefinition::class))->addFlags(new ApiAware()),

            (new EnumField('status', 'status', VoucherStatus::WaitingValidOrder))->addFlags(new Required(), new ApiAware()),

            (new OneToManyAssociationField('transactions', GiftCardTransactionDefinition::class, 'voucher_id'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('auditLogs', GiftCardAuditLogDefinition::class, 'voucher_id'))->addFlags(new ApiAware()),

            new CustomFields(),
        ]);
    }
}
