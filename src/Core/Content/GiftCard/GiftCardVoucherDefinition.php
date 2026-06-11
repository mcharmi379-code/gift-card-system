<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCard;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GiftCardVoucherDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ictech_gift_card_voucher';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardVoucherEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('gift_card_id', 'giftCardId', GiftCardDefinition::class))->addFlags(new Required()),
            new FkField('order_id', 'orderId', \Shopware\Core\Checkout\Order\OrderDefinition::class),
            new FkField('customer_id', 'customerId', \Shopware\Core\Checkout\Customer\CustomerDefinition::class),
            (new StringField('code', 'code'))->addFlags(new Required()),
            (new FloatField('original_amount', 'originalAmount'))->addFlags(new Required()),
            (new FloatField('remaining_balance', 'remainingBalance'))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', \Shopware\Core\System\Currency\CurrencyDefinition::class))->addFlags(new Required()),
            new StringField('sender_name', 'senderName'),
            (new StringField('recipient_name', 'recipientName'))->addFlags(new Required()),
            (new StringField('recipient_email', 'recipientEmail'))->addFlags(new Required()),
            new LongTextField('personal_message', 'personalMessage'),
            (new DateField('scheduled_send_date', 'scheduledSendDate'))->addFlags(new Required()),
            new DateTimeField('sent_at', 'sentAt'),
            (new DateField('expires_at', 'expiresAt'))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
        ]);
    }
}
