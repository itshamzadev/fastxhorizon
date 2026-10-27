# Manual Payment Configuration Guide

Open Admin → Payment Settings. The page supports the three payment switches and a bounded JSON editor for manual methods. Each method needs a stable lowercase `key`, name, instructions, recipient details, enabled flag, version and a `fields` array.

Supported field types are `text`, `textarea`, `number`, `select`, `radio`, `checkbox` and `file`. Field IDs must be stable alphanumeric identifiers. Select/radio fields use an `options` array. File fields are limited server-side to safe image/PDF MIME types and 5 MB maximum.

Do not enter secrets, executable content, HTML or account details that have not been approved by the company. Automatic gateways and COD remain disabled until an operational workflow is approved.
