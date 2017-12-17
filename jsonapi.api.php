<?php

/**
 * @file
 * Documentation related to JSON API.
 */

/**
 * @defgroup jsonapi_normalizer_architecture JSON API Normalizer Architecture
 * @{
 *
 * @section resources Resources
 * The unit of data in the JSON API spec is a "resource", and relationships
 * between those units of data are called "relationships". The Drupal module
 * that implements JSON API exposes every entity as a resource, every entity
 * bundle as a resource type and every entity reference field as a relationship.
 *
 * While it is theoretically possible to expose arbitrary data as resources, the
 * decision to limit to only (config and content) entities means that all
 * relationships between entities (resources) and entity types (resource types)
 * are available automatically, without the need for another abstraction layer.
 *
 * The JSON API module can be summarized as just that: logic that exposes Drupal
 * entities according to the JSON API spec.*
 *
 *
 * @section normalizers Normalizers
 * The JSON API module reuses as many of Drupal core's Serialization module's
 * normalizers as possible.
 *
 * Since the JSON API module follows the http://jsonapi.org/ spec, which
 * requires special handling for resources (entities), relationships between
 * resources (entity references) and resource IDs (entity UUIDs), it has no
 * choice but to override the Serialization module's normalizers for entities,
 * fields and entity reference fields.
 *
 * This also means that contributed/custom modules that provide additional field
 * types need to implement normalizers not at the field level ("FieldType"
 * plugins), but at a level below that ("DataType" plugins). Otherwise they will
 * not have any effect.
 *
 * A benefit of implementing normalizers at that lower level is that they then
 * work automatically for both the JSON API module and core's REST module.
 *
 *
 * @section api API
 * The JSON API module provides a HTTP API, that follows the jsonapi.org spec.
 *
 * The JSON API module does not provide a PHP API to modify its behavior. It is
 * designed to be "zero-configuration".
 *
 * - Adding new resources/resource types is unsupported: all entities/entity
 *   types are exposed automatically. If you want to expose more data via JSON
 *   API, make sure they're entities. See the "Resources" section.
 * - Customizing the normalization of fields is not supported: only normalizers
 *   for "DataType" plugins are supported (a level below fields).
 *
 * The JSON API module does provide a PHP API to generate JSON API
 * representations of entities:
 * @code
 * \Drupal::service('jsonapi.entity.to_jsonapi')->serialize($entity)
 * @endcode
 *
 *
 * @section bc Backwards Compatibility
 * PHP API: there is no PHP API, which means this module's implementation
 * details are free to change at any time.
 * (Also note that normalizers are internal implementation details: they are
 * services due to the design of the Symfony Serialization component, not
 * because the JSON API module wanted to expose services.)
 *
 * HTTP API: bugfixes made to comply better with the jsonapi.org spec are never
 * considered backwards compatibility breaks. URLs and response structure will
 * never be changed: backwards compatibility is guaranteed for those.
 *
 * @}
 */
