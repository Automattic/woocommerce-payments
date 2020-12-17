module TableCard = {
  type cell
  type row = array<cell>

  type column<'columnKey> = {
    key: 'columnKey,
    label: string,
    isNumeric: option<bool>,
    isLeftAligned: option<bool>,
    defaultOrder: option<string>,
    cellClassName: option<string>,
    required: option<bool>,
  }

  @bs.module("@woocommerce/components") @react.component
  external make: (
    ~title: string,
    ~isLoading: bool,
    ~rowsPerPage: float,
    ~totalRows: float,
    ~headers: array<column<'columnKey>>,
    ~rows: array<row>,
    ~query: Externals_WooCommerce_Navigation.query,
    ~onQueryChange: Externals_WooCommerce_Navigation.function,
  ) => React.element = "TableCard"

  let make_column = (
    ~key,
    ~label,
    ~required=?,
    ~isLeftAligned=?,
    ~isNumeric=?,
    ~defaultOrder=?,
    ~cellClassName=?,
    (),
  ) => {
    key: key,
    label: label,
    isLeftAligned: isLeftAligned,
    isNumeric: isNumeric,
    defaultOrder: defaultOrder,
    cellClassName: cellClassName,
    required: required,
  }

  // This is an unfortunate hack we need to use because the value in each cell can be either
  // a string, number, or bool, which we would normally model with a Variant in ReScript. However,
  // Variant types are wrapped in an object once compiled to JS, and as such not compatible with
  // how TableCard expects each cell to be structured.
  // So, we just embed raw JS here that accepts a value and display object and returns an object
  // that's correctly formatted for TableCard.
  let make_cell = %raw(`
  function(value, display) {
	  return { value: value, display: display }
  }
  `)
}

module Link = {
  @bs.module("@woocommerce/components") @react.component
  external make: (~href: string, ~children: React.element) => React.element = "Link"
}
