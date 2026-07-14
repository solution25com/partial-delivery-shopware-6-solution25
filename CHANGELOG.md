# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-07-14
### Added
- Added a generic Integration API for external systems to create, update, list, and capture partial deliveries
- Added idempotent payment capture support
- Added payment provider interface for registering capture handlers
- Added capture lifecycle events for audit and reconciliation
- Added configurable capture update policy for already-captured deliveries
- Added integration fields to partial delivery records
- Added integration documentation and Postman collection

### Changed
- Migrated persistence layer to Shopware DAL
- Legacy admin UI endpoints remain unchanged and fully backward compatible

## [1.1.1] - 2026-04-22

### Fixed
- **Reset button not working when creating a shipment**
- **Reset button not working when editing a shipment**

### Added
- **Confirmation dialog when deleting a shipment**
---

## [1.1.0] - 2026-04-20
### Added
- **Compatibility with Shopware 6.7**
### Fixed
- **Shipment form still showing after submitting it**

---


## [1.0.0] - 2025-05-20
### Key Features
- **Partial Shipment Management**
- **New Admin Tab: Shipments**
- **Multiple Shipments per Line Item**

---

###  Technical Requirements

- **Shopware Commercial Edition is required.**  
  Make sure the [Shopware Commercial extension](https://docs.shopware.com/en/shopware-6-en/extensions/shopware-commercial) is installed and active.

---


