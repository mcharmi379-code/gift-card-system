<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCard;

use ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateDefinition;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class GiftCardDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'ictech_gift_card';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GiftCardCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new Required(), new ApiAware()),
            (new StringField('code_prefix', 'codePrefix'))->addFlags(new ApiAware()),
            (new IntField('validity_days', 'validityDays'))->addFlags(new Required(), new ApiAware()),
            (new IntField('quantity', 'quantity'))->addFlags(new ApiAware()),
            (new IntField('quantity_issued', 'quantityIssued'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new Required(), new ApiAware()),
            (new BoolField('restrict_combine', 'restrictCombine'))->addFlags(new ApiAware()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('template_id', 'templateId', GiftCardTemplateDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('template', 'template_id', GiftCardTemplateDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('vouchers', GiftCardVoucherDefinition::class, 'gift_card_id'))->addFlags(new ApiAware(), new SetNullOnDelete()),

            new CustomFields(),
        ]);
    }
}
