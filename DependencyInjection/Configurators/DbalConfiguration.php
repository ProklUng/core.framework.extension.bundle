<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Class DbalConfiguration
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators
 *
 * @internal fork from vendor/doctrine/doctrine-bundle/DependencyInjection/Configuration.php
 */
class DbalConfiguration
{
    /**
     * Add DBAL section to configuration tree.
     *
     * @param ArrayNodeDefinition $node Node.
     *
     * @return void
     */
    public function addDbalSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
            ->arrayNode('dbal')
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return is_array($v) && ! array_key_exists('connections', $v) && ! array_key_exists('connection', $v);
            })
            ->then(static function ($v) {
                // Key that should not be rewritten to the connection config
                $excludedKeys = ['default_connection' => true, 'types' => true, 'type' => true];
                $connection   = [];
                foreach ($v as $key => $value) {
                    if (isset($excludedKeys[$key])) {
                        continue;
                    }

                    $connection[$key] = $v[$key];
                    unset($v[$key]);
                }

                $v['default_connection'] = isset($v['default_connection']) ? (string) $v['default_connection'] : 'default';
                $v['connections']        = [$v['default_connection'] => $connection];

                return $v;
            })
            ->end()
            ->children()
            ->booleanNode('enabled')->defaultValue(false)->end()
            ->scalarNode('default_connection')->end()
            ->scalarNode('url')->end()
            ->scalarNode('charset')->end()
            ->end()
            ->fixXmlConfig('type')
            ->children()
            ->arrayNode('types')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->beforeNormalization()
            ->ifString()
            ->then(static function ($v) {
                return ['class' => $v];
            })
            ->end()
            ->children()
            ->scalarNode('class')->isRequired()->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->fixXmlConfig('connection')
            ->append($this->getDbalConnectionsNode())
            ->end();
    }

    /**
     * Return the dbal connections node
     */
    private function getDbalConnectionsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('connections');
        $node        = $treeBuilder->getRootNode();

        $connectionNode = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array');
        assert($connectionNode instanceof ArrayNodeDefinition);

        $this->configureDbalDriverNode($connectionNode);

        $connectionNode
            ->fixXmlConfig('option')
            ->fixXmlConfig('mapping_type')
            ->fixXmlConfig('slave')
            ->fixXmlConfig('replica')
            ->fixXmlConfig('shard')
            ->fixXmlConfig('default_table_option')
            ->children()
            ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
            ->scalarNode('platform_service')->end()
            ->booleanNode('auto_commit')->end()
            ->scalarNode('schema_filter')->end()
            ->booleanNode('logging')->defaultValue(false)->end()
            ->booleanNode('profiling')->defaultValue(false)->end()
            ->booleanNode('profiling_collect_backtrace')
            ->defaultValue(false)
            ->info('Enables collecting backtraces when profiling is enabled')
            ->end()
            ->booleanNode('profiling_collect_schema_errors')
            ->defaultValue(true)
            ->info('Enables collecting schema errors when profiling is enabled')
            ->end()
            ->scalarNode('server_version')->end()
            ->scalarNode('driver_class')->end()
            ->scalarNode('wrapper_class')->end()
            ->scalarNode('shard_manager_class')->end()
            ->scalarNode('shard_choser')->end()
            ->scalarNode('shard_choser_service')->end()
            ->booleanNode('keep_replica')->end()
            ->arrayNode('options')
            ->useAttributeAsKey('key')
            ->prototype('variable')->end()
            ->end()
            ->arrayNode('mapping_types')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->arrayNode('default_table_options')
            ->info("This option is used by the schema-tool and affects generated SQL. Possible keys include 'charset','collate', and 'engine'.")
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->end();

        // dbal >= 2.11
        $replicaNode = $connectionNode
            ->children()
            ->arrayNode('replicas')
            ->useAttributeAsKey('name')
            ->prototype('array');
        $this->configureDbalDriverNode($replicaNode);

        $shardNode = $connectionNode
            ->children()
            ->arrayNode('shards')
            ->prototype('array');

        // TODO: Remove when https://github.com/psalm/psalm-plugin-symfony/pull/168 is released
        assert($shardNode instanceof ArrayNodeDefinition);

        $shardNode
            ->children()
            ->integerNode('id')
            ->min(1)
            ->isRequired()
            ->end()
            ->end();
        $this->configureDbalDriverNode($shardNode);

        return $node;
    }

    /**
     * Adds config keys related to params processed by the DBAL drivers
     *
     * These keys are available for replica configurations too.
     * @param ArrayNodeDefinition $node
     */
    private function configureDbalDriverNode(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
            ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
            ->scalarNode('dbname')->end()
            ->scalarNode('host')->info('Defaults to "localhost" at runtime.')->end()
            ->scalarNode('port')->info('Defaults to null at runtime.')->end()
            ->scalarNode('user')->info('Defaults to "root" at runtime.')->end()
            ->scalarNode('password')->info('Defaults to null at runtime.')->end()
            ->booleanNode('override_url')->defaultValue(false)->info('Allows overriding parts of the "url" parameter with dbname, host, port, user, and/or password parameters.')->end()
            ->scalarNode('application_name')->end()
            ->scalarNode('charset')->end()
            ->scalarNode('path')->end()
            ->booleanNode('memory')->end()
            ->scalarNode('unix_socket')->info('The unix socket to use for MySQL')->end()
            ->booleanNode('persistent')->info('True to use as persistent connection for the ibm_db2 driver')->end()
            ->scalarNode('protocol')->info('The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)')->end()
            ->booleanNode('service')
            ->info('True to use SERVICE_NAME as connection parameter instead of SID for Oracle')
            ->end()
            ->scalarNode('servicename')
            ->info(
                'Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter ' .
                'for Oracle depending on the service parameter.'
            )
            ->end()
            ->scalarNode('sessionMode')
            ->info('The session mode to use for the oci8 driver')
            ->end()
            ->scalarNode('server')
            ->info('The name of a running database server to connect to for SQL Anywhere.')
            ->end()
            ->scalarNode('default_dbname')
            ->info(
                'Override the default database (postgres) to connect to for PostgreSQL connexion.'
            )
            ->end()
            ->scalarNode('sslmode')
            ->info(
                'Determines whether or with what priority a SSL TCP/IP connection will be negotiated with ' .
                'the server for PostgreSQL.'
            )
            ->end()
            ->scalarNode('sslrootcert')
            ->info(
                'The name of a file containing SSL certificate authority (CA) certificate(s). ' .
                'If the file exists, the server\'s certificate will be verified to be signed by one of these authorities.'
            )
            ->end()
            ->scalarNode('sslcert')
            ->info(
                'The path to the SSL client certificate file for PostgreSQL.'
            )
            ->end()
            ->scalarNode('sslkey')
            ->info(
                'The path to the SSL client key file for PostgreSQL.'
            )
            ->end()
            ->scalarNode('sslcrl')
            ->info(
                'The file name of the SSL certificate revocation list for PostgreSQL.'
            )
            ->end()
            ->booleanNode('pooled')->info('True to use a pooled server with the oci8/pdo_oracle driver')->end()
            ->booleanNode('MultipleActiveResultSets')->info('Configuring MultipleActiveResultSets for the pdo_sqlsrv driver')->end()
            ->booleanNode('use_savepoints')->info('Use savepoints for nested transactions')->end()
            ->scalarNode('instancename')
            ->info(
                'Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection.' .
                ' It is generally used to connect to an Oracle RAC server to select the name' .
                ' of a particular instance.'
            )
            ->end()
            ->scalarNode('connectstring')
            ->info(
                'Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.' .
                'When using this option, you will still need to provide the user and password parameters, but the other ' .
                'parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods' .
                ' from Doctrine\DBAL\Connection will no longer function as expected.'
            )
            ->end()
            ->end()
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return ! isset($v['sessionMode']) && isset($v['session_mode']);
            })
            ->then(static function ($v) {
                $v['sessionMode'] = $v['session_mode'];
                unset($v['session_mode']);

                return $v;
            })
            ->end()
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return ! isset($v['MultipleActiveResultSets']) && isset($v['multiple_active_result_sets']);
            })
            ->then(static function ($v) {
                $v['MultipleActiveResultSets'] = $v['multiple_active_result_sets'];
                unset($v['multiple_active_result_sets']);

                return $v;
            })
            ->end()
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return empty($v['override_url']) && isset($v['url']);
            })
            ->then(static function ($v) {
                @trigger_error('Not setting doctrine.dbal.override_url to true is deprecated. True is the only value that will be supported in doctrine-bundle 3.0.', E_USER_DEPRECATED);

                return $v;
            })
            ->end();
    }
}