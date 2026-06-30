<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardAuditLog;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherDefinition;
use ICTECHGiftCard\Core\Enum\AuditLogAction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class GiftCardAuditLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'ictech_gift_card_audit_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardAuditLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GiftCardAuditLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('voucher_id', 'voucherId', GiftCardVoucherDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('voucher', 'voucher_id', GiftCardVoucherDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('admin_user_id', 'adminUserId', \Shopware\Core\System\User\UserDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('adminUser', 'admin_user_id', \Shopware\Core\System\User\UserDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new EnumField('action', 'action', AuditLogAction::Revoke))->addFlags(new Required(), new ApiAware()),

            (new StringField('old_value', 'oldValue'))->addFlags(new ApiAware()),
            (new StringField('new_value', 'newValue'))->addFlags(new ApiAware()),
            (new LongTextField('reason', 'reason'))->addFlags(new ApiAware()),
        ]);
    }
}
