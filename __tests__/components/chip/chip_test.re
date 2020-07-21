open Jest;

describe("Chip (ReasonML)", () => {
  open Expect;
  open ReactTestingLibrary;

  let renderChip = (chipType, message) => {
    <Chip chipType message /> |> render |> container;
  };

  test("Renders an alert chip", () => {
    renderChip(Alert, "Alert message") |> expect |> toMatchSnapshot
  });

  test("Renders a primary chip", () => {
    renderChip(Primary, "Primary message") |> expect |> toMatchSnapshot
  });

  test("Renders a light chip", () => {
    renderChip(Light, "Light message") |> expect |> toMatchSnapshot
  });

  test("Renders a warning chip", () => {
    renderChip(Warning, "Alert message") |> expect |> toMatchSnapshot
  });

  test("Renders primary when using default type", () => {
    renderChip(Default, "Message") |> expect |> toMatchSnapshot
  });

  test("Renders primary when no type provided", () => {
    <Chip message="Message" />
    |> render
    |> container
    |> expect
    |> toMatchSnapshot
  });
});
