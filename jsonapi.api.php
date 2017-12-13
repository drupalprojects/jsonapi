<?php

/**
 * @file
 * Documentation related to JSON API.
 */

/**
 * @defgroup jsonapi_normalizer_architecture JSON API Normalizer Architecture
 * @{
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
 * @}
 */
