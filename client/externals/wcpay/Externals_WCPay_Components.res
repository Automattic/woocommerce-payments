module ClickableCell = {
  @bs.module("components/clickable-cell") @react.component
  external make: (~href: string, ~children: React.element) => React.element = "default"
}

module DetailsLink = {
  @bs.module("components/details-link") @react.component
  external make: (~id: string, ~parentSegment: string) => React.element = "default"

  @bs.module("components/details-link")
  external getDetailsURL: (string, string) => string = "getDetailsURL"
}
