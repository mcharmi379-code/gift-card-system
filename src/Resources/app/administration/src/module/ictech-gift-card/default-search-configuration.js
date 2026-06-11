const { searchRankingPoint } = Shopware.Service('searchRankingService');

const defaultSearchConfiguration = {
    _searchable: false,
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    codePrefix: {
        _searchable: true,
        _score: searchRankingPoint.LOW_SEARCH_RANKING,
    },
};

export default defaultSearchConfiguration;
