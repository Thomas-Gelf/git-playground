<?php

namespace Icinga\Module\Idbdash\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Application\Benchmark;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Common\SearchControls;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Icingadb\Widget\ItemList\HostList;
use Icinga\Module\Icingadb\Widget\ItemTable\HostItemTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use Icinga\Module\Idbdash\IcingaDbLookup;
use Icinga\Module\Idbdash\IcingaHost;
use Icinga\Module\Idbdash\TimePeriodHelper;
use ipl\Orm\Query;
use ipl\Orm\UnionQuery;
use ipl\Stdlib\Filter\All;
use ipl\Stdlib\Filter\Equal;
use ipl\Stdlib\Filter\Rule;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

class DashboardController extends CompatController
{
    use Auth;
    use Controls;
    use Database;
    use SearchControls;

    protected ?string $format = null;

    /** @var ?Rule Filter from query string parameters */
    private ?Rule $filter = null;

    public function init()
    {
        $this->handleSortControlSubmit();

        $this->format = $this->params->shift('format'); // TODO: restrict
    }
    /**
     * Get the filter created from query string parameters
     */
    public function getFilter(): Rule
    {
        if ($this->filter === null) {
            $this->filter = QueryString::parse((string) $this->params);

            $db = new IcingaDbLookup();
            $active = TimePeriodHelper::filterActiveTimePeriods($db->loadTimePeriods());
            $filters = array_map(fn ($period) => new Equal('host.vars.sla', $period->name), $active);
            $this->filter->add(new All(...$filters));
        }

        return $this->filter;
    }

    public function filter(Query $query, ?Rule $filter = null): self
    {
        if ($this->format !== 'sql' || $this->hasPermission('config/authentication/roles/show')) {
            $this->applyRestrictions($query);
        }

        if ($query instanceof UnionQuery) {
            foreach ($query->getUnions() as $query) {
                $query->filter($filter ?: $this->getFilter());
            }
        } else {
            $query->filter($filter ?: $this->getFilter());
        }

        return $this;
    }

    public function hostsAction()
    {
        $compact = $this->view->compact;
        $this->params->shift('view');
        $this->addSingleTab($this->translate('Hosts'));
        $this->addTitle($this->translate('Hosts'));
        $this->content()->addAttributes(['class' => 'icinga-module module-icingadb']);
        $db = $this->getDb();

        $hosts = IcingaHost::on($db)->with(['state', 'icon_image', 'state.last_comment']);
        $hosts->getWith()['host.state']->setJoinType('INNER');
        $hosts->setResultSetClass(VolatileStateResults::class);

        $this->params->shift('limit');
        $this->params->shift('page');

        $hosts->orderBy('host.state.severity');
        if ($columnString = $this->params->shift('columns', '')) {
            foreach (explode(',', $columnString) as $column) {
                if ($column = trim($column)) {
                    $columns[] = $column;
                }
            }
        } else {
            $columns = ['host.name', 'host.state.output', 'host.vars.os'];
        }
        // TODO: limit, page?

        $hosts->withColumns($columns);
        $this->filter($hosts, $this->getFilter());
        $hosts->peekAhead($compact);

        $results = $hosts->execute();
        $hostList = (new HostList($results));
        $hostList->setViewMode('tabular');
        $hostList = (new HostItemTable($results, HostItemTable::applyColumnMetaData($hosts, $columns)))
            ->setSort('host.status.severity');


        $this->content()->add($hostList);
        // yield $this->export($hosts);

        $this->content()->add(
            (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit', 'view'])))
                ->setBaseTarget('_next')
                ->setAttribute('title', sprintf(
                    t('Show all %d hosts'),
                    $hosts->count()
                ))
        );

        $this->setAutorefreshInterval(10);
    }
}
