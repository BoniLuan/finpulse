from app.providers.fake import FakeProvider


def test_investment_intent_extracts_amount_and_months() -> None:
    intent = FakeProvider().parse_intent("quanto rende 10 mil na poupança em 2 anos?")
    assert intent["type"] == "investment_return"
    assert intent["params"]["principal"] == 10_000
    assert intent["params"]["months"] == 24
    assert intent["params"]["indicator"] == "poupanca"


def test_inflation_intent() -> None:
    intent = FakeProvider().parse_intent("quanto R$1000 valem hoje corrigidos pela inflação?")
    assert intent["type"] == "inflation_correction"
    assert intent["params"]["amount"] == 1000


def test_indicator_value_intent_defaults_to_selic() -> None:
    intent = FakeProvider().parse_intent("qual o cdi atual?")
    assert intent["type"] == "indicator_value"
    assert intent["params"]["indicator"] == "cdi"


def test_explain_investment() -> None:
    answer = FakeProvider().explain(
        {"type": "investment_return"},
        {
            "type": "investment_return",
            "principal": 1000.0,
            "months": 12,
            "indicator": "poupanca",
            "monthly_rate_pct": 0.5,
            "result": 1061.68,
            "earnings": 61.68,
        },
    )
    assert "1061.68" in answer
