from app.providers.gemini import _to_intent


def test_to_intent_collects_only_present_params() -> None:
    out = _to_intent(
        {
            "type": "investment_return",
            "principal": 1000,
            "months": 12,
            "indicator": "cdi",
            "percent_of_cdi": 110,
            "amount": None,
        }
    )
    assert out["type"] == "investment_return"
    assert out["params"] == {
        "principal": 1000,
        "months": 12,
        "indicator": "cdi",
        "percent_of_cdi": 110,
    }


def test_to_intent_defaults_type_and_empty_params() -> None:
    out = _to_intent({})
    assert out["type"] == "indicator_value"
    assert out["params"] == {}
